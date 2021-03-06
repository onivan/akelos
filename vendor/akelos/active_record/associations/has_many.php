<?php

/**
 * Adds the following methods for retrieval and query of collections of associated objects.
 * collection is replaced with the singular form of current association,
 * so var $has_many = 'clients' would hold an array of objects on $this->clients
 * and a collection handling interface instance on $this->client (singular form)
 *
 * * collection->load($force_reload = false) - returns an array of all the associated objects. An empty array is returned if none are found.
 * * collection->add($object, ?) - adds one or more objects to the collection by setting their foreign keys to the collection's primary key.
 * (collection->push and $collection->concat are aliases to this method).
 * * collection->delete($object, ?) - removes one or more objects from the collection by setting their foreign keys to NULL. This will also destroy the objects if they?re declared as belongs_to and dependent on this model.
 * * collection->set($objects) - replaces the collections content by deleting and adding objects as appropriate.
 * * collection->setByIds($ids) - replace the collection by the objects identified by the primary keys in ids
 * * collection->clear() - removes every object from the collection. This destroys the associated objects if they are 'dependent', deletes them directly from the database if they are 'dependent' => 'delete_all', and sets their foreign keys to NULL otherwise.
 * * collection->isEmpty() - returns true if there are no associated objects.
 * * collection->getSize() - returns the number of associated objects.
 * * collection->find() - finds an associated object according to the same rules as ActiveRecord->find.
 * * collection->count() - returns the number of elements associated.  (collection->size() is an alias to this method)
 * * collection->build($attributes = array()) - returns a new object of the collection type that has been instantiated with attributes and linked to this object through a foreign key but has not yet been saved. *Note:* This only works if an associated object already exists, not if it?s null
 * * collection->create($attributes = array()) - returns a new object of the collection type that has been instantiated with attributes and linked to this object through a foreign key and that has already been saved (if it passed the validation). *Note:* This only works if an associated object already exists, not if it?s null
 *
 * Example: A Firm class declares has_many clients, which will add:
 *
 *  * Firm->client->load() (similar to $Clients->find('all', array('conditions' => 'firm_id = '.$id)) )
 *  * Firm->client->add()
 *  * Firm->client->delete()
 *  * Firm->client->assign()
 *  * Firm->client->assignByIds()
 *  * Firm->client->clear()
 *  * Firm->client->isEmpty() (similar to count($Firm->clients) == 0)
 *  * Firm->client->getSize() (similar to Client.count "firm_id = #{id}")
 *  * Firm->client->find() (similar to $Client->find($id, array('conditions' => 'firm_id = '.$id)) )
 *  * Firm->client->build() (similar to new Client(array('firm_id' => $id)) )
 *  * Firm->client->create() (similar to $c = new Client(array('firm_id' => $id)); $c->save(); return $c )
 *
 * The declaration can also include an options array to specialize the behavior of the association.
 *
 * Options are:
 *
 *  * 'class_name' - specify the class name of the association. Use it only if that name can't be inferred from the association name. So "$has_many = 'products'" will by default be linked to the Product class, but if the real class name is SpecialProduct, you?ll have to specify it with this option.
 *  * 'conditions' - specify the conditions that the associated objects must meet in order to be included as a "WHERE" sql fragment, such as "price > 5 AND name LIKE ?B%?".
 *  * 'order' - specify the order in which the associated objects are returned as a "ORDER BY" sql fragment, such as "last_name, first_name DESC"
 *  * 'group' - specify the attribute by which the associated objects are returned as a "GROUP BY" sql fragment, such as "category"
 *  * 'foreign_key' - specify the foreign key used for the association. By default this is guessed to be the name of this class in lower-case and "_id" suffixed. So a Person class that makes a has_many association will use "person_id" as the default foreign_key.
 *  * 'dependent' - if set to 'destroy' all the associated objects are destroyed alongside this object by calling their destroy method. If set to 'delete_all' all associated objects are deleted without calling their destroy method. If set to 'nullify' all associated objects? foreign keys are set to NULL without calling their save callbacks.
 *  * 'finder_sql' - specify a complete SQL statement to fetch the association. This is a good way to go for complex associations that depend on multiple tables. Note: When this option is used, findInCollection is not added.
 *  * 'counter_sql' - specify a complete SQL statement to fetch the size of the association. If +'finder_sql'+ is specified but +'counter_sql'+, +'counter_sql'+ will be generated by replacing SELECT ? FROM with SELECT COUNT(*) FROM.
 *  * 'include' - specify second-order associations that should be eager loaded when the collection is loaded.
 *  * 'group' An attribute name by which the result should be grouped. Uses the GROUP BY SQL-clause.
 *  * 'limit' An integer determining the limit on the number of rows that should be returned.
 *  * 'offset' An integer determining the offset from where the rows should be fetched. So at 5, it would skip the first 4 rows.
 *
 * Option examples:
 *
 * $has_many = array(
 *                  'comments'  => array('order' => 'posted_on', 'include' => 'author', 'dependent' => 'nullify'),
 *                  'people'    => array('conditions' => 'deleted = 0', 'order' => 'name'),
 *                  'tracks'    => array('order' => 'position', 'dependent' => 'destroy'),
 *                  'members'   => array('class_name' => 'Person', 'conditions' => 'role = "merber"'));
 */
class AkHasMany extends AkAssociation
{
    public
    $associated_ids = array(),
    $association_id;

    public function &addAssociated($association_id, $options = array()) {

        $default_options = array(
        'class_name' => empty($options['class_name']) ? AkInflector::classify($association_id) : $options['class_name'],
        'conditions' => false,
        'order' => false,
        'include_conditions_when_included' => true,
        'include_order_when_included' => true,
        'foreign_key' => false,
        'dependent' => 'nullify',
        'finder_sql' => false,
        'counter_sql' => false,
        'include' => false,
        'instantiate' => false,
        'group' => false,
        'limit' => false,
        'offset' => false,
        'handler_name' => strtolower(AkInflector::underscore(AkInflector::singularize($association_id))),
        );

        $options = array_merge($default_options, $options);

        $options['foreign_key'] = empty($options['foreign_key']) ? AkInflector::underscore($this->Owner->getModelName()).'_id' : $options['foreign_key'];

        $Collection = $this->_setCollectionHandler($association_id, $options['handler_name']);
        $Collection->setOptions($association_id, $options);


        $this->addModel($association_id,  $Collection);

        if($options['instantiate']){
            $associated = $Collection->load();
        }

        $this->setAssociatedId($association_id, $options['handler_name']);
        $Collection->association_id = $association_id;

        return $Collection;
    }

    public function getType() {
        return 'hasMany';
    }

    public function &_setCollectionHandler($association_id, $handler_name) {
        if(isset($this->Owner->$association_id)){
            if(!is_array($this->Owner->$association_id)){
                trigger_error(Ak::t('%model_name::%association_id is not a collection array on current %association_id hasMany association',array('%model_name'=>$this->Owner->getModelName(), '%association_id'=>$association_id)), E_USER_NOTICE);
            }
            $associated = $this->Owner->$association_id;
        }else{
            $associated = array();
            $this->Owner->$association_id = $associated;
        }

        if(isset($this->Owner->$handler_name)){
            trigger_error(Ak::t('Could not load %association_id on %model_name because "%model_name->%handler_name" attribute '.
            'is already defined and can\'t be used as an association placeholder',
            array('%model_name'=>$this->Owner->getModelName(),'%association_id'=>$association_id, '%handler_name'=>$handler_name)),
            E_USER_ERROR);
            return false;
        }else{
            $this->Owner->$handler_name = new AkHasMany($this->Owner);
        }
        return $this->Owner->$handler_name;
    }

    public function &load($force_reload = false) {
        $options = $this->getOptions($this->association_id);
        if($force_reload || empty($this->Owner->{$options['handler_name']}->_loaded)){
            if(!$this->Owner->isNewRecord()){
                $this->constructSql(false);
                $options = $this->getOptions($this->association_id);
                $Associated = $this->getAssociatedModelInstance();
                $finder_options = array('conditions'=>$options['finder_sql']);

                //TODO: refactorize this
                if(!empty($options['order'])){
                    $finder_options['order'] = $options['order'];
                }
                if(!empty($options['group'])){
                    $finder_options['group'] = $options['group'];
                }
                if(!empty($options['include'])){
                    $finder_options['include'] = $options['include'];
                }

                if($FoundAssociates = $Associated->find('all',$finder_options)){
                    array_map(array($this,'_setAssociatedMemberId'),$FoundAssociates);
                    $this->Owner->{$this->association_id} = $FoundAssociates;
                }
            }
            if(empty($this->Owner->{$this->association_id})){
                $this->Owner->{$this->association_id} = array();
            }

            $this->Owner->{$options['handler_name']}->_loaded = true;
        }
        return $this->Owner->{$this->association_id};
    }

    /**
     * add($object), add(array($object, $object2)) - adds one or more objects to the collection by setting
     * their foreign keys to the collection?s primary key. Items are saved automatically when parent has been saved.
     */
    public function add(&$Associated) {
        if(is_array($Associated)){
            $succes = true;
            $succes = $this->Owner->notifyObservers('beforeAdd') ? $succes : false;
            $options = $this->getOptions($this->association_id);
            foreach (array_keys($Associated) as $k){
                if($succes && !empty($options['before_add']) && method_exists($this->Owner, $options['before_add']) && $this->Owner->{$options['before_add']}($Associated[$k]) === false ){
                    $succes = false;
                }
                if($succes && !$this->_hasAssociatedMember($Associated[$k])){
                    $this->Owner->{$this->association_id}[] = $Associated[$k];
                    $this->_setAssociatedMemberId($Associated[$k]);
                    if($this->_relateAssociatedWithOwner($Associated[$k])){

                        $succes = $Associated[$k]->save() ? $succes : false;

                        if($succes && !empty($options['after_add']) && method_exists($this->Owner, $options['after_add']) && $this->Owner->{$options['after_add']}($Associated[$k]) === false ){
                            $succes = false;
                        }
                    }
                }
            }
            $succes = $this->Owner->notifyObservers('afterAdd') ? $succes : false;
            return $succes;
        }else{
            $associates = array();
            $associates[] = $Associated;
            return $this->add($associates);
        }
    }

    public function push(&$record) {
        return $this->add($record);
    }

    public function concat(&$record) {
        return $this->add($record);
    }

    /**
    * Remove all records from this association
    */
    public function deleteAll($Skip = null) {
        $this->load();
        return $this->delete($this->Owner->{$this->association_id}, $Skip);
    }

    public function reset() {
        $options = $this->getOptions($this->association_id);
        $this->Owner->{$options['handler_name']}->_loaded = false;
    }

    public function set(&$objects) {
        $this->deleteAll($objects);
        $this->add($objects);
    }

    public function setIds() {
        $ids = func_get_args();
        $ids = is_array($ids[0]) ? $ids[0] : $ids;

        $AssociatedModel = $this->getAssociatedModelInstance();
        if(!empty($ids)){
            $NewAssociates = $AssociatedModel->find($ids);
            $this->set($NewAssociates);
        }
    }

    public function setByIds() {
        $ids = func_get_args();
        call_user_func_array(array($this,'setIds'), $ids);
    }

    public function addId($id) {
        $AssociatedModel = $this->getAssociatedModelInstance();
        if($NewAssociated = $AssociatedModel->find($id)){
            return $this->add($NewAssociated);
        }
        return false;
    }


    public function delete(&$Associated, $Skip = null) {
        $success = true;
        if(!is_array($Associated)){
            $associated_elements = array();
            $associated_elements[] = $Associated;
            return $this->delete($associated_elements, $Skip);
        }else{
            $options = $this->getOptions($this->association_id);

            $ids_to_skip = array();
            $Skip = empty($Skip) ? null : (is_array($Skip) ? $Skip : array($Skip));
            if(!empty($Skip)){
                foreach (array_keys($Skip) as $k){
                    $ids_to_skip[] = $Skip[$k]->getId();
                }
            }

            $ids_to_nullify = array();
            $ids_to_delete = array();
            $items_to_remove_from_collection = array();
            $AssociatedModel = $this->getAssociatedModelInstance();

            $owner_type = $this->_findOwnerTypeForAssociation($AssociatedModel, $this->Owner);

            foreach (array_keys($Associated) as $k){
                $items_to_remove_from_collection[] = $Associated[$k]->getId();
                if(!in_array($Associated[$k]->getId() , $ids_to_skip)){
                    switch ($options['dependent']) {
                        case 'destroy':
                            $success = $Associated[$k]->destroy() ? $success : false;
                            break;
                        case 'delete_all':
                            $ids_to_delete[] = $Associated[$k]->getId();
                            break;
                        case 'nullify':
                            $id_to_nullify = $Associated[$k]->quotedId();
                            if(!empty($id_to_nullify)){
                                $ids_to_nullify[] = $id_to_nullify;
                            }
                        default:
                            break;
                    }
                }
            }

            $ids_to_nullify = empty($ids_to_nullify) ? false : array_diff($ids_to_nullify,array(''));
            if(!empty($ids_to_nullify)){
                $success = $AssociatedModel->updateAll(
                ' '.$options['foreign_key'].' = NULL ',
                ' '.$options['foreign_key'].' = '.$this->Owner->quotedId().' AND '.$AssociatedModel->getPrimaryKey().' IN ('.join(', ',$ids_to_nullify).')'
                ) ? $success : false;
            }elseif(!empty($ids_to_delete)){
                $success = $AssociatedModel->delete($ids_to_delete) ? $success : false;
            }

            $this->removeFromCollection($items_to_remove_from_collection);
        }

        return $success;
    }

    /**
    * Remove records from the collection. Use delete() in order to trigger database dependencies
    */
    public function removeFromCollection(&$records) {
        if(!is_array($records)){
            $records_array = array();
            $records_array[] = $records;
            $this->delete($records_array);
        }else{
            $this->Owner->notifyObservers('beforeRemove');
            $options = $this->getOptions($this->association_id);
            foreach (array_keys($records) as $k){

                if(!empty($options['before_remove']) && method_exists($this->Owner, $options['before_remove']) && $this->Owner->{$options['before_remove']}($records[$k]) === false ){
                    continue;
                }

                if(isset($records[$k]->__activeRecordObject)){
                    $record_id = $records[$k]->getId();
                }else{
                    $record_id = $records[$k];
                }

                foreach (array_keys($this->Owner->{$this->association_id}) as $kk){
                    if(
                    (
                    !empty($this->Owner->{$this->association_id}[$kk]->__hasManyMemberId) &&
                    !empty($records[$k]->__hasManyMemberId) &&
                    $records[$k]->__hasManyMemberId == $this->Owner->{$this->association_id}[$kk]->__hasManyMemberId
                    ) || (
                    ($this->Owner->{$this->association_id}[$kk] instanceof AkActiveRecord) &&
                    $record_id == $this->Owner->{$this->association_id}[$kk]->getId()
                    )
                    ){
                        unset($this->Owner->{$this->association_id}[$kk]);
                    }
                }

                $this->_unsetAssociatedMemberId($records[$k]);

                if(!empty($options['after_remove']) && method_exists($this->Owner, $options['after_remove'])){
                    $this->Owner->{$options['after_remove']}($records[$k]);
                }

            }
            $this->Owner->notifyObservers('afterRemove');
        }
    }

    public function _setAssociatedMemberId(&$Member) {
        if(empty($Member->__hasManyMemberId)) {
            $Member->__hasManyMemberId = Ak::randomString();
        }
        $object_id = method_exists($Member,'getId') ? $Member->getId() : null;
        if(!empty($object_id)){
            $this->associated_ids[$object_id] = $Member->__hasManyMemberId;
        }
    }

    public function _unsetAssociatedMemberId(&$Member) {
        $id = $this->_getAssociatedMemberId($Member);
        unset($this->associated_ids[$id]);
        unset($Member->__hasManyMemberId);
    }

    public function _getAssociatedMemberId(&$Member) {
        if(!empty($Member->__hasManyMemberId)) {
            return array_search($Member->__hasManyMemberId, $this->associated_ids);
        }
        return false;
    }

    public function _hasAssociatedMember(&$Member) {
        return !empty($Member->__hasManyMemberId);
    }

    public function _relateAssociatedWithOwner(&$Associated) {
        if(!$this->Owner->isNewRecord()){
            if(method_exists($Associated, 'getModelName')){
                $foreign_key = $this->getOption($this->association_id, 'foreign_key');
                if($this->getOption($this->association_id, 'class_name') != $Associated->getModelName() || $foreign_key == $Associated->get($foreign_key)){
                    return false;
                }
                $Associated->set($foreign_key, $this->Owner->getId());

                /**
                 * Set the Owner as belongsTo association, if defined
                 */
                $associatedIds=$Associated->getAssociatedIds();
                foreach($associatedIds as $assoc_id) {
                    $collection_handler=$Associated->getCollectionHandlerName($assoc_id);
                    $handler=empty($collection_handler)?$assoc_id:$collection_handler;
                    if($Associated->$handler->getType()=='belongsTo' &&
                    $this->Owner->getType()==$Associated->$handler->getAssociationOption('class_name')
                    ) {
                        $Associated->$handler->_AssociationHandler->_build($assoc_id,$this->Owner);
                    }
                }

                return true;
            }
        }
        return false;
    }

    public function &_build($association_id, &$AssociatedObject, $reference_associated = true) {
        if($reference_associated){
            $this->Owner->$association_id = $AssociatedObject;
        }else{
            $this->Owner->$association_id = $AssociatedObject;
        }
        $this->Owner->$association_id->_AssociationHandler = $this;
        $this->Owner->$association_id->_associatedAs = $this->getType();
        $this->Owner->$association_id->_associationId = $association_id;
        $this->Owner->_associations[$association_id] = $this->Owner->$association_id;
        return $this->Owner->$association_id;
    }

    public function constructSql($set_owner_table_has_included = true) {
        $options = $this->getOptions($this->association_id);
        $Associated = $this->getAssociatedModelInstance();
        $owner_id = $this->Owner->quotedId();
        $table_name = (!empty($options['include']) || $set_owner_table_has_included) ? '__owner' : $Associated->getTableName();

        if(empty($options['finder_sql'])){
            $options['finder_sql'] = ' '.$table_name.'.'.$options['foreign_key'].' = '.(empty($owner_id) ? 'null' : $owner_id).' ';
            $options['finder_sql'] .= !empty($options['conditions']) ? ' AND '.$options['conditions'].' ' : '';
        } else {
            /**
             * we have a finder_sql and we replace placeholders for the association:
             *
             * :foreign_key_value
             */
            $options['finder_sql'] = str_replace(array(':foreign_key_value'),array($owner_id), $options['finder_sql']);
        }
        if (isset($options['group'])) {
            $options['group'] = str_replace(array(':foreign_key_value'),array($owner_id), $options['group']);
        }

        if(empty($options['counter_sql']) && !empty($options['finder_sql'])){
            $options['counter_sql'] = $options['finder_sql'];
        }elseif(empty($options['counter_sql'])){
            $options['counter_sql'] = ' '.$table_name.'.'.$options['foreign_key'].' = '.(empty($owner_id) ? 'null' : $owner_id).' ';
            $options['counter_sql'] .= !empty($options['conditions']) ? ' AND '.$options['conditions'].' ' : '';
        }elseif(!empty($options['counter_sql'])) {
            $options['counter_sql'] = str_replace(array(':foreign_key_value'),array($owner_id), $options['counter_sql']);
        }

        if(!empty($options['counter_sql']) && strtoupper(substr($options['counter_sql'],0,6)) != 'SELECT'){
            $count_table_name = $table_name == '__owner' ?  $Associated->getTableName().' as __owner' : $table_name;
            $options['counter_sql'] = 'SELECT COUNT(*) FROM '.$count_table_name.' WHERE '.$options['counter_sql'];
        }

        $this->setOptions($this->association_id, $options);
    }

    public function count($force_count = false) {
        $count = 0;
        $options = $this->getOptions($this->association_id);
        if($force_count || (empty($this->Owner->{$options['handler_name']}->_loaded) && !$this->Owner->isNewRecord())){
            $this->constructSql(false);
            $options = $this->getOptions($this->association_id);
            $Associated = $this->getAssociatedModelInstance();

            if($this->_hasCachedCounter()){
                $count = $Associated->getAttribute($this->_getCachedCounterAttributeName());
            }elseif(!empty($options['counter_sql'])){
                $count = $Associated->countBySql($options['counter_sql']);
            }else{
                $count = (strtoupper(substr($options['finder_sql'],0,6)) != 'SELECT') ?
                $Associated->count($options['foreign_key'].'='.$this->Owner->quotedId()) :
                $Associated->countBySql($options['finder_sql']);
            }
        }else{
            $count = count($this->Owner->{$this->association_id});
        }

        if($count == 0){
            $this->Owner->{$this->association_id} = array();
            $this->Owner->{$options['handler_name']}->_loaded = true;
        }

        return $count;
    }

    public function size() {
        return $this->count();
    }

    public function &build($attributes = array(), $set_as_new_record = true) {
        $options = $this->getOptions($this->association_id);
        Ak::import($options['class_name']);
        $record = new $options['class_name']($attributes);
        $record->_newRecord = $set_as_new_record;
        $this->Owner->{$this->association_id}[] = $record;
        $this->_setAssociatedMemberId($record);
        $this->_relateAssociatedWithOwner($record);
        return $record;
    }

    public function &create($attributes = array()) {
        $record = $this->build($attributes);
        if(!$this->Owner->isNewRecord()){
            $record->save();
        }
        return $record;
    }

    public function getAssociatedFinderSqlOptions($association_id, $options = array()) {
        $options = $this->getOptions($this->association_id);
        $Associated = $this->getAssociatedModelInstance();
        $table_name = $Associated->getTableName();
        $owner_id = $this->Owner->quotedId();

        $finder_options = array();

        foreach ($options as $option=>$value) {
            if(!empty($value)){
                $finder_options[$option] = trim($Associated->addTableAliasesToAssociatedSql('_'.$this->association_id, $value));
            }
        }

        $finder_options['joins'] = $this->constructSqlForInclusion();
        $finder_options['selection'] = '';

        foreach (array_keys($Associated->getColumns()) as $column_name){
            $finder_options['selection'] .= '_'.$this->association_id.'.'.$column_name.' AS _'.$this->association_id.'_'.$column_name.', ';
        }

        $finder_options['selection'] = trim($finder_options['selection'], ', ');

        $finder_options['conditions'] = empty($options['conditions']) ? '' :

        $Associated->addTableAliasesToAssociatedSql('_'.$this->association_id, $options['conditions']).' ';

        return $finder_options;
    }

    public function getAssociatedFinderSqlOptionsForInclusionChain($prefix, $parent_handler_name, $options = array(),$pluralize=false) {

        $association_options = $this->getOptions($this->association_id);
        $options = array_merge($association_options,$options);
        $Associated = $this->getAssociatedModelInstance();
        $pk=$Associated->getPrimaryKey();
        $table_name = $Associated->getTableName();
        $owner_id = $this->Owner->quotedId();
        $handler_name = $options['handler_name'];
        $finder_options = array();

        foreach ($options as $option=>$value) {
            if(!empty($value) && !is_bool($value)){
                if(is_string($value)) {
                    $finder_options[$option] = trim($Associated->addTableAliasesToAssociatedSql($parent_handler_name.'__'.$handler_name, $value));
                } else if(is_array($value)) {

                    foreach($value as $idx=>$v) {
                        $value[$idx]=trim($Associated->addTableAliasesToAssociatedSql($parent_handler_name.'__'.$handler_name, $v));
                    }
                    $finder_options[$option] = $value;
                }else {
                    $finder_options[$option] = $value;
                }
            }
        }

        $finder_options['joins'] = $this->constructSqlForInclusionChain($this->association_id,$handler_name,$parent_handler_name);
        $finder_options['selection'] = '';

        $selection_parenthesis = $this->_getColumnParenthesis();//
        foreach (array_keys($Associated->getColumns()) as $column_name){
            $finder_options['selection'] .= $parent_handler_name.'__'.$handler_name.'.'.$column_name.' AS '.$selection_parenthesis.$prefix.'['.$handler_name.']'.($pluralize?'[@'.$pk.']':'').'['.$column_name.']'.$selection_parenthesis.', ';
        }

        $finder_options['selection'] = trim($finder_options['selection'], ', ');

        $finder_options['conditions'] = empty($finder_options['conditions']) ? '' :

        $Associated->addTableAliasesToAssociatedSql($parent_handler_name.'__'.$handler_name, $options['conditions']).' ';

        return $finder_options;
    }

    public function constructSqlForInclusion() {
        $Associated = $this->getAssociatedModelInstance();
        $options = $this->getOptions($this->association_id);
        return ' LEFT OUTER JOIN '.
        $Associated->getTableName().' AS _'.$this->association_id.
        ' ON '.
        '__owner.'.$this->Owner->getPrimaryKey().
        ' = '.
        '_'.$this->association_id.'.'.$options['foreign_key'].' ';
    }

    public function constructSqlForInclusionChain($association_id,$handler_name, $parent_handler_name) {
        $Associated = $this->getAssociatedModelInstance();
        $options = $this->getOptions($this->association_id);
        //$handler_name = $options['handler_name'];
        return ' LEFT OUTER JOIN '.
        $Associated->getTableName().' AS '.$parent_handler_name.'__'.$handler_name.
        ' ON '.
        $parent_handler_name.'.'.$this->Owner->getPrimaryKey().
        ' = '.
        ''.$parent_handler_name.'__'.$handler_name.'.'.$options['foreign_key'].' ';
    }

    public function _hasCachedCounter() {
        $Associated = $this->getAssociatedModelInstance();
        return $Associated->isAttributePresent($this->_getCachedCounterAttributeName());
    }

    public function _getCachedCounterAttributeName() {
        return $this->association_id.'_count';
    }

    public function &getAssociatedModelInstance() {
        static $ModelInstances;
        $class_name = $this->getOption($this->association_id, 'class_name');
        if(empty($ModelInstances[$class_name])){
            Ak::import($class_name);
            $ModelInstances[$class_name] = new $class_name();
        }
        return $ModelInstances[$class_name];
    }

    public function &find() {
        $result = false;
        if(!$this->Owner->isNewRecord()){

            $args = func_get_args();
            $num_args = func_num_args();

            if(!empty($args[$num_args-1]) && is_array($args[$num_args-1])){
                $options_in_args = true;
                $options = $args[$num_args-1];
            }else{
                $options_in_args = false;
                $options = array();
            }

            $this->constructSql(!empty($options['include']));
            $has_many_options = $this->getOptions($this->association_id);
            $Associated = $this->getAssociatedModelInstance();
            if (empty($options['conditions'])) {
                $options['conditions'] = @$has_many_options['finder_sql'];
            } elseif(!empty($has_many_options['finder_sql']) && is_array($options['conditions']) && !strstr($options['conditions'][0], $has_many_options['finder_sql'])) {
                $options['conditions'][0] .= ' AND '. $has_many_options['finder_sql'];
            } elseif (!empty($has_many_options['finder_sql']) && !strstr($options['conditions'], $has_many_options['finder_sql'])) {
                $options['conditions'] .= ' AND '. $has_many_options['finder_sql'];
            }

            $options['bind'] = empty($options['bind']) ? @$has_many_options['bind'] : $options['bind'];
            $options['order'] = empty($options['order']) ? @$has_many_options['order'] : $options['order'];
            $options['group'] = empty($options['group']) ? @$has_many_options['group'] : $options['group'];
            $options['include'] = empty($options['include']) ? @$has_many_options['include'] : $options['include'];

            if (!empty($options['bind'])) {
                $options['bind'] = Ak::toArray($options['bind']);
                $options['bind'] = array_diff($options['bind'],array(''));
                $options['conditions'] = is_array($options['conditions'])?$options['conditions']:array($options['conditions']);
                $options['conditions'] = array_merge($options['conditions'],$options['bind']);
                unset($options['bind']);
            }

            if($options_in_args){
                $args[$num_args-1] = $options;
            }else{
                $args = empty($args) ? array('all') : $args;
                array_push($args, $options);
            }

            $result = call_user_func_array(array($Associated,'find'), $args);
        }

        return $result;
    }

    public function isEmpty() {
        return $this->count() === 0;
    }

    public function getSize() {
        return $this->count();
    }

    public function clear() {
        return $this->deleteAll();
    }

    /**
    * Triggers
    */
    public function afterCreate(&$object) {
        return $this->_afterCallback($object);
    }

    public function afterUpdate(&$object) {
        return $this->_afterCallback($object);
    }

    public function beforeDestroy(&$object) {
        $success = true;

        foreach ((array)$object->_associationIds as $k => $v){
            if(isset($object->$k) && is_array($object->$k) && isset($object->$v) && method_exists($object->$v, 'getType') && $object->$v->getType() == 'hasMany'){

                $ids_to_delete = array();
                $ids_to_nullify = array();
                $items_to_remove_from_collection = array();

                $object->$v->load();
                foreach(array_keys($object->$k) as $key){

                    $items_to_remove_from_collection[] = $object->{$k}[$key];

                    switch ($object->$v->options[$k]['dependent']) {

                        case 'destroy':
                            $success = $object->{$k}[$key]->destroy() ? $success : false;
                            break;

                        case 'delete_all':
                            $ids_to_delete[] = $object->{$k}[$key]->getId();
                            break;

                        case 'nullify':
                            $id_to_nullify = $object->{$k}[$key]->quotedId();
                            if(!empty($id_to_nullify)){
                                $ids_to_nullify[] = $id_to_nullify;
                            }
                            break;

                        default:
                            break;
                    }
                }

                $ids_to_nullify = empty($ids_to_nullify) ? false : array_diff($ids_to_nullify,array(''));
                if(!empty($ids_to_nullify)){
                    $success = $object->{$k}[$key]->updateAll(
                    ' '.$object->$v->options[$k]['foreign_key'].' = NULL ',
                    ' '.$object->$v->options[$k]['foreign_key'].' = '.$object->quotedId().' AND '.$object->{$k}[$key]->getPrimaryKey().' IN ('.join(', ', $ids_to_nullify).')'
                    ) ? $success : false;
                }elseif(!empty($ids_to_delete)){
                    $success = $object->{$k}[$key]->delete($ids_to_delete) ? $success : false;
                }
                $object->$v->removeFromCollection($items_to_remove_from_collection);
            }
        }

        return $success;
    }

    public function _afterCallback(&$object) {
        $success = true;
        $object_id = $object->getId();
        foreach (array_keys($object->hasMany->models) as $association_id){
            $CollectionHandler = $object->hasMany->models[$association_id];
            $foreign_key = $CollectionHandler->getOption($association_id, 'foreign_key');
            $class_name = strtolower($CollectionHandler->getOption($association_id, 'class_name'));
            if(!empty($object->$association_id) && is_array($object->$association_id)){
                foreach (array_keys($object->$association_id) as $k){
                    if(!empty($object->{$association_id}[$k]) && strtolower(get_class($object->{$association_id}[$k])) == strtolower($class_name)){
                        $AssociatedItem = $object->{$association_id}[$k];
                        $AssociatedItem->set($foreign_key, $object_id);
                        $success = !$AssociatedItem->save() ? false : $success;
                    }
                }
            }
        }
        return $success;
    }
}

