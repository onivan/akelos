<?php

class AkXdocToText
{
    public function convert() {
        $xdoc2txt_bin = AK_CONTRIB_DIR.DS.'hyperestraier'.DS.'xdoc2txt.exe';

        if(!AK_WIN){
            trigger_error(Ak::t('Xdoc2Text is a windows only application. Please use wvWare instead'), E_USER_WARNING);
            return false;
        }
        if(!file_exists($xdoc2txt_bin)){
            trigger_error(Ak::t('Could not find xdoc2txt.exe on %path. Please download it from http://www31.ocn.ne.jp/~h_ishida/xdoc2txt.html',array('%path'=>$xdoc2txt_bin)),E_USER_WARNING);
            return false;
        }

        exec('@"'.$xdoc2txt_bin . '" -f "' . $this->source_file . '" "' . $this->destination_file.'"');

        $result = Ak::file_get_contents($this->destination_file);
        $this->delete_source_file ? @Ak::file_delete($this->source_file) : null;
        $this->keep_destination_file ? null : Ak::file_delete($this->destination_file);

        return $result;
    }

    public function init() {
        $this->ext = empty($this->ext) ? 'doc' : strtolower(trim($this->ext,'.'));
        $this->tmp_name = Ak::randomString();
        if(empty($this->source_file)){
            $this->source_file = AK_TMP_DIR.DS.$this->tmp_name.'.'.$this->ext;
            Ak::file_put_contents($this->source_file,$this->source);
            $this->delete_source_file = true;
            $this->keep_destination_file = empty($this->keep_destination_file) ? (empty($this->destination_file) ? false : true) : $this->keep_destination_file;
        }else{
            $this->delete_source_file = false;
            $this->keep_destination_file = true;
        }

        $this->convert_to = 'txt';
        $this->destination_file_name = empty($this->destination_file_name) ? $this->tmp_name.'.'.$this->convert_to : $this->destination_file_name.(strstr($this->destination_file_name,'.') ? '' : '.'.$this->convert_to);
        $this->destination_file = empty($this->destination_file) ? AK_TMP_DIR.DS.$this->destination_file_name : $this->destination_file;
    }
}

