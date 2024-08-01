<?php
namespace RevisionControl\Model\Table;

use BaserCore\Model\Table\AppTable;
use Cake\ORM\Association\hasOne;

/**
 * [RevisionControl]
 *
 */

class RevisionControlsTable extends AppTable {
	
	// public $name = 'RevisionControl';
	// public $plugin = 'RevisionControl';

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('revision_controls');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        // $this->addBehavior('BaserCore.BcUpload', [
        //     'saveDir' => \Cake\Core\Configure::read('RevisionControl.filesDir'),
        //     'fields' => [
        //     ]
        // ]);
        // // RevisionControls に Userd を１対１の関係で関連づける
        // $this->hasOne('BaserCore.Users', [
        //     'className' => 'Users',
        //     'foreignKey' => 'user_id',
        //     'propertyName' => 'Users'
        // ])
        //  ->setForeignKey('user_id')
        //     ->setName('Users')
        //     ->setProperty('users');
    }


}
