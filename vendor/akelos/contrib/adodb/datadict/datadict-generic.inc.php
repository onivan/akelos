<?php

/**
  V5.09 25 June 2009   (c) 2000-2009 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.

  Set tabs to 4 for best viewing.

*/

// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB2_generic extends ADODB_DataDict {

	public $databaseType = 'generic';
	public $seqField = false;

 	public function ActualType($meta)
	{
		switch($meta) {
		case 'C': return 'VARCHAR';
		case 'XL':
		case 'X': return 'VARCHAR(250)';

		case 'C2': return 'VARCHAR';
		case 'X2': return 'VARCHAR(250)';

		case 'B': return 'VARCHAR';

		case 'D': return 'DATE';
		case 'TS':
		case 'T': return 'DATE';

		case 'L': return 'DECIMAL(1)';
		case 'I': return 'DECIMAL(10)';
		case 'I1': return 'DECIMAL(3)';
		case 'I2': return 'DECIMAL(5)';
		case 'I4': return 'DECIMAL(10)';
		case 'I8': return 'DECIMAL(20)';

		case 'F': return 'DECIMAL(32,8)';
		case 'N': return 'DECIMAL';
		default:
			return $meta;
		}
	}

	function AlterColumnSQL($tabname, $flds, $tableflds='',$tableoptions='')
	{
		if ($this->debug) ADOConnection::outp("AlterColumnSQL not supported");
		return array();
	}


	public function DropColumnSQL($tabname, $flds, $tableflds='',$tableoptions='')
	{
		if ($this->debug) ADOConnection::outp("DropColumnSQL not supported");
		return array();
	}
}