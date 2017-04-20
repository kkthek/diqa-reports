<?php
use DIQA\Util\DBHelper;
/**
 *
 */

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Creates tables required for ODBcore
 *
 * @ingroup Maintenance
 */
class Setup extends Maintenance {


	public function __construct() {
		parent::__construct();
		$this->mDescription = "";
	}

	public function execute() {
		
		$db = wfGetDB( DB_MASTER );
		DBHelper::setupTable('diqareports_job_progress', array(
            'id' 	=> 'INT(8) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
            'user_id' => 'INT(10) UNSIGNED NOT NULL',
            'job_key' => 'VARCHAR(255) NOT NULL',
            'job_category' => 'VARCHAR(255) NOT NULL',
            'started_at' => 'DATETIME NOT NULL',
            'status' => 'INT(8) NOT NULL',
            'total' => 'INT(8) NOT NULL',
			'status_text' => 'VARCHAR(4095)'),
		$db, true);
		$this->output( "done.\n" );
	}
}



$maintClass = "Setup";
require_once RUN_MAINTENANCE_IF_MAIN;
