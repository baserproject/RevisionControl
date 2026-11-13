<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

/**
 * [RevisionControl]
 *
 */

class CreateRevisionControls extends BcMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-up-method
     * @return void
     */
    public function up()
    {
        $this->table('revision_controls', [
            'collation' => 'utf8mb4_general_ci'
         ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'comment' => '作成日'
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'comment' => '更新日'
            ])
            ->addColumn('model_name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
                'comment' => 'モデル'
            ])
            ->addColumn('model_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'comment' => 'エンティティID'
            ])
            ->addColumn('revision', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('deta_object', 'text', [
                'default' => null,
                'limit' => 4294967295,
                'null' => true,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-down-method
     * @return void
     */
    public function down()
    {
        $this->table('revision_controls')->drop()->save();
    }
}