<?php

/**
* Adds the following methods for retrieval and query of a single associated object.
* $association is replaced with the symbol passed as the first argument, so
* <tt>hasOne('manager')</tt> would add among others <tt>$this->manager->getAttributes()</tt>.
*
* Example: An Account class declares <tt>hasOne('beneficiary');</tt>, which will add:
* * <tt>$Account->beneficiary->load()</tt> (similar to <tt>$Beneficiary->find('first', array('conditions' => "account_id = $id"))</tt>)
* * <tt>$Account->beneficiary->assign($Beneficiary);</tt> (similar to <tt>$Beneficiary->account_id = $Account->id; $Beneficiary->save()</tt>)
* * <tt>$Account->beneficiary->build();</tt> (similar to <tt>$Beneficiary = new Beneficiary("account_id->", $Account->id)</tt>)
* * <tt>$Account->beneficiary->create();</tt> (similar to <tt>$b = new Beneficiary("account_id->", $Account->id); $b->save(); $b</tt>)
*
* The declaration can also include an options array to specialize the behavior of the association.
*
* Options are:
* * <tt>class_name</tt>  - specify the class name of the association. Use it only if that name can't be inferred
*   from the association name. So <tt>hasOne('manager')</tt> will by default be linked to the "Manager" class, but
*   if the real class name is "Person", you'll have to specify it with this option.
* * <tt>conditions</tt>  - specify the conditions that the associated object must meet in order to be included as a "WHERE"
*   sql fragment, such as "rank = 5".
* * <tt>order</tt>       - specify the order from which the associated object will be picked at the top. Specified as
*    an "ORDER BY" sql fragment, such as "last_name, first_name DESC"
* * <tt>dependent</tt>   - if set to true, the associated object is destroyed when this object is. It's also destroyed if another
*   association is assigned.
* * <tt>foreign_key</tt> - specify the foreign key used for the association. By default this is guessed to be the name
*   of this class in lower-case and "_id" suffixed. So a "Person" class that makes a hasOne association will use "person_id"
*   as the default foreign_key.
*
* Option examples:
*   var $hasOne = array(
*       'credit_card' => array('dependent' => true),
*       'last_comment' => array('class_name' => "Comment", 'order' => "posted_on"),
*       'project_manager' => array('class_name' => "Person", 'conditions' => "role = 'project_manager'")
*       );
*/
class AkHasOne extends AkAssociation
{
    public
    $associated_ids = array();

    public function &addAssociated($association_id, $options = array()) {
        $default_options = array(
        'class_name' => empty($options['class_name']) ? AkInflector::camelize($association_id) : $options['class_name'],
        'foreign_key' => empty($options['foreign_key']) ? AkInflector::singularize($this->Owner->getTableName()).'_id' : $options['foreign_key'],
        'remote'=>false,
        'instantiate'=>false,
        'conditions'=>false,
        'include_conditions_when_included'=>true,
        'order'=>false,
        'include_order_when_included'=>true,
        'dependent'=>false,
        'join_conditions'=>false
        );

        $options = array_merge($default_options, $options);

        $options['table_name'] = empty($options['table_name']) ? AkInflector::tableize($options['class_name']) : $options['table_name'];

        $this->setOptions($association_id, $options);

        $this->addModel($association_id,  new AkAssociatedActiveRecord());

        $associated = $this->getModel($association_id);
        $this->setAssociatedId($association_id, $associated->getId());

        $associated = $this->_build($association_id, $associated, false);

        $this->_saveLoadedHandler($association_id, $associated);

        if($options['instantiate']){
            $associated = $this->addModel($association_id,  new $options['class_name']($options['foreign_key'].' = '.$this->Owner->quotedId()));
        }

        return $associated;
    }

    /**
     * Assigns the associate object, extracts the primary key, sets it as the foreign key, and saves the associate object.
     */
    public function &assign($association_id, &$Associated) {
        if(!$this->Owner->isNewRecord()){
            $Associated->set($this->Owner->$association_id->getAssociationOption('foreign_key'), $this->Owner->getId());
            $Associated->save();
        }

        $this->_build($association_id, $Associated);
        $this->Owner->$association_id->_loaded = true;
        return $Associated;
    }

    public function getAssociatedId($association_id) {
        return isset($this->associated_ids[$association_id]) ? $this->associated_ids[$association_id] : false;
    }


    public function getType() {
        return 'hasOne';
    }


    public function getAssociatedFinderSqlOptions($association_id, $options = array()) {
        $default_options = array(
        'conditions' => $this->Owner->$association_id->getAssociationOption('include_conditions_when_included'),
        'order' => $this->Owner->$association_id->getAssociationOption('include_order_when_included')
        );

        if(!($this->Owner->$association_id instanceof AkActiveRecord)){
            $this->build($association_id, array(), false);
        }

        $table_name = $this->Owner->$association_id->getTableName();
        $options = array_merge($default_options, $options);

        $finder_options = array();

        foreach ($options as $option=>$available) {

            $value = $this->Owner->$association_id->getAssociationOption($option);
            if (!empty($available)) {

                $value=$available;
            }
            if (!empty($value)) {
                if (is_string($value)) {
                    $finder_options[$option] = trim($this->Owner->$association_id->addTableAliasesToAssociatedSql('_'.$association_id, $value));
                } else {
                    $finder_options[$option] = $value;
                }
            }

        }

        $finder_options['joins'] = $this->Owner->$association_id->constructSqlForInclusion();

        $finder_options['selection'] = '';
        foreach (array_keys($this->Owner->$association_id->getColumns()) as $column_name){
            $finder_options['selection'] .= '_'.$association_id.'.'.$column_name.' AS _'.$association_id.'_'.$column_name.', ';
        }
        $finder_options['selection'] = trim($finder_options['selection'], ', ');

        return $finder_options;
    }

    public function getAssociatedFinderSqlOptionsForInclusionChain($association_id, $prefix, $parent_handler_name, $options = array(),$pluralize=false) {

        $default_options = array(
        'conditions' => $this->Owner->$association_id->getAssociationOption('include_conditions_when_included'),
        'order' => $this->Owner->$association_id->getAssociationOption('include_order_when_included')
        );
        $handler_name = $association_id;
        if(!($this->Owner->$association_id instanceof AkActiveRecord)){
            $this->build($association_id, array(), false);
        }
        $options = array_merge($default_options, $options);
        $pk=$this->Owner->$association_id->getPrimaryKey();
        $finder_options = array();

        foreach ($options as $option=>$available) {

            $value = $this->Owner->$association_id->getAssociationOption($option);

            if ((!empty($available) && $available!==true)|| $available===false) {

                $value=$available;
            }
            if (!empty($value) && !is_bool($value)) {
                if (is_string($value)) {
                    $finder_options[$option] = trim($this->Owner->$association_id->addTableAliasesToAssociatedSql($parent_handler_name.'__'.$handler_name, $value));
                } else {
                    $finder_options[$option] = $value;
                }
            }

        }
        $finder_options['joins'] = $this->Owner->$association_id->constructSqlForInclusionChain($handler_name, $parent_handler_name);
        $selection_parenthesis = $this->_getColumnParenthesis();//
        $finder_options['selection'] = '';
        foreach (array_keys($this->Owner->$association_id->getColumns()) as $column_name){
            $finder_options['selection'] .= $parent_handler_name.'__'.$handler_name.'.'.$column_name.' AS '.$selection_parenthesis.$prefix.'['.$handler_name.']'.($pluralize?'[@'.$pk.']':'').'['.$column_name.']'.$selection_parenthesis.', ';

        }
        $finder_options['selection'] = trim($finder_options['selection'], ', ');

        return $finder_options;
    }

    public function constructSqlForInclusion($association_id) {
        return ' LEFT OUTER JOIN '.
        $this->Owner->$association_id->getTableName().' AS _'.$association_id.
        ' ON '.
        '__owner.'.$this->Owner->getPrimaryKey().
        ' = '.
        '_'.$association_id.'.'.$this->Owner->$association_id->getAssociationOption('foreign_key').' ';
    }

    function constructSqlForInclusionChain($association_id,$handler_name, $parent_handler_name) {
        $return=' LEFT OUTER JOIN '.
        $this->Owner->$association_id->getTableName().' AS '.$parent_handler_name.'__'.$handler_name.
        ' ON '.
        $parent_handler_name.'.'.$this->Owner->getPrimaryKey().
        ' = '.
        ''.$parent_handler_name.'__'.$handler_name.'.'.$this->Owner->$association_id->getAssociationOption('foreign_key').' ';
        $join_conditions = $this->Owner->$association_id->getAssociationOption('join_conditions');
        if($join_conditions) {
            $return.=' AND '.$join_conditions;
        }
        return $return;
    }

    public function &build($association_id, $attributes = array(), $replace_existing = true) {
        $class_name = $this->Owner->$association_id->getAssociationOption('class_name');
        $foreign_key = $this->Owner->$association_id->getAssociationOption('foreign_key');
        Ak::import($class_name);
        $record = new $class_name($attributes);
        if ($replace_existing){
            $record = $this->replace($association_id, $record, true);
        }
        if(!$this->Owner->isNewRecord()){
            $record->set($foreign_key, $this->Owner->getId());
        }

        $record = $this->_build($association_id, $record);

        return $record;
    }

    /**
    * Returns a new object of the associated type that has been instantiated with attributes
    * and linked to this object through a foreign key and that has already been
    * saved (if it passed the validation)
    */
    public function &create($association_id, $attributes = array(), $replace_existing = true) {
        $this->build($association_id, $attributes, $replace_existing);
        $this->Owner->$association_id->save();
        $this->Owner->$association_id->_loaded = true;
        return $this->Owner->$association_id;
    }

    public function &replace($association_id, &$NewAssociated, $dont_save = false) {
        $Associated = $this->loadAssociated($association_id);
        if(($Associated instanceof AkActiveRecord) && ($NewAssociated instanceof AkActiveRecord) && $Associated->getId() == $NewAssociated->getId()){
            return $NewAssociated;
        }

        if($Associated instanceof AkActiveRecord){
            if ($Associated->getAssociationOption('dependent') && !$dont_save){
                if(!$Associated->isNewRecord()){
                    $Associated->destroy();
                }
            }elseif(!$dont_save){
                $Associated->set($Associated->getAssociationOption('foreign_key'), null);
                if($Associated->isNewRecord()){
                    $Associated->save();
                }
            }
        }

        $result = false;

        if ($NewAssociated instanceof AkActiveRecord){
            if(!$this->Owner->isNewRecord()){
                $NewAssociated->set($Associated->getAssociationOption('foreign_key'), $this->Owner->getId());
            }

            $NewAssociated = $this->_build($association_id, $NewAssociated);

            $NewAssociated->_loaded = true;
            if(!$NewAssociated->isNewRecord() || !$dont_save){
                if($NewAssociated->save()){
                    return $NewAssociated;
                }
            }else{
                return $NewAssociated;
            }
        }
        return $result;
    }

    public function &findAssociated($association_id) {
        $false = false;
        if(!$this->Owner->getId()){
            return $false;
        }
        if(!($this->Owner->$association_id instanceof AkActiveRecord)){
            $this->build($association_id, array(), false);
        }

        $table_name = $this->Owner->$association_id->getAssociationOption('table_name');

        $finder_options =         array(
        'conditions' => trim($this->Owner->$association_id->addTableAliasesToAssociatedSql($table_name, $this->constructSqlConditions($association_id))),
        'selection' => $table_name,
        'joins' => trim($this->Owner->$association_id->addTableAliasesToAssociatedSql($table_name, $this->constructSql($association_id))),
        'order' => trim($this->Owner->$association_id->addTableAliasesToAssociatedSql($table_name, $this->Owner->$association_id->getAssociationOption('order')))
        );

        /**
        * todo we will use a select statement later
        */
        $sql = $this->Owner->constructFinderSqlWithAssociations($finder_options, false);//.' LIMIT 1';
        if($results = $this->Owner->$association_id->findBySql($sql)){
            return $results[0];
        }

        return $false;
    }


    public function constructSqlConditions($association_id) {
        $foreign_key = $this->Owner->$association_id->getAssociationOption('foreign_key');
        $conditions = $this->Owner->$association_id->getAssociationOption('conditions');

        $foreign_key_value = $this->Owner->getId();
        if(empty($foreign_key_value)){
            return $conditions;
        }
        $foreign_key_value=$this->Owner->castAttributeForDatabase($foreign_key, $foreign_key_value);
        if(empty($foreign_key_value)) $foreign_key_value=-1;
        return (empty($conditions) ? '' : $conditions.' AND ').$foreign_key.' = '.$foreign_key_value;
    }

    public function constructSql($association_id) {
        $foreign_key = $this->Owner->$association_id->getAssociationOption('foreign_key');
        $table_name = $this->Owner->$association_id->getAssociationOption('table_name');
        $owner_table = $this->Owner->getTableName();

        return ' LEFT OUTER JOIN '.$owner_table.' ON '.$owner_table.'.'.$this->Owner->getPrimaryKey().' = '.$table_name.'.'.$foreign_key;
    }


    /**
     * Triggers
     */
    public function afterSave(&$object) {
        $success = true;
        $associated_ids = $object->getAssociatedIds();

        foreach ($associated_ids as $associated_id){
            if($object->$associated_id instanceof AkActiveRecord){

                if(strtolower($object->hasOne->getOption($associated_id, 'class_name')) == strtolower($object->$associated_id->getType())){
                    $object->hasOne->replace($associated_id, $object->$associated_id, false);
                    $object->$associated_id->set($object->hasOne->getOption($associated_id, 'foreign_key'), $object->getId());
                    $success = $object->$associated_id->save() ? $success : false;

                }elseif($object->$associated_id->getType() == 'hasOne'){
                    $attributes = array();
                    foreach ((array)$object->$associated_id as $k=>$v){
                        $k[0] != '_' ? $attributes[$k] = $v : null;
                    }
                    $attributes = array_diff($attributes, array(''));
                    if(!empty($attributes)){
                        $object->hasOne->build($associated_id, $attributes);
                    }
                }
            }
        }
        return $success;
    }

    public function afterDestroy(&$object) {
        $success = true;
        $associated_ids = $object->getAssociatedIds();
        foreach ($associated_ids as $associated_id){
            if( isset($object->$associated_id->_associatedAs) &&
            $object->$associated_id->_associatedAs == 'hasOne' &&
            $dependency=$object->$associated_id->getAssociationOption('dependent')){
                if ($object->$associated_id->getType() == 'hasOne'){
                    $object->$associated_id->load();
                }
                if(empty($object->$associated_id->id) || $object->$associated_id->getType() == 'hasOne' || $object->$associated_id->isNewRecord()) return true;
                switch ($dependency) {

                    case 'delete':
                        if(method_exists($object->$associated_id, 'delete')){
                            $success = $object->$associated_id->delete($object->$associated_id->getId()) ? $success : false;
                        }
                        break;
                    case 'nullify':
                        if(method_exists($object->$associated_id, 'updateAttribute')){
                            $success = $object->$associated_id->updateAttribute($object->$associated_id->getAssociationOption('foreign_key'),null) ? $success : false;
                        }
                        break;
                    case 'destroy':
                    default:

                        if(method_exists($object->$associated_id, 'destroy')){
                            $success = $object->$associated_id->destroy() ? $success : false;
                        }
                        break;
                }
            }
        }
        return $success;
    }
}

