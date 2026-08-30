<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @since         5.3.0
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace RevisionControl\Test\TestCase\Model\Table;

use BaserCore\TestSuite\BcTestCase;
use Cake\ORM\TableRegistry;

/**
 * RevisionControlsTableTest
 */
class RevisionControlsTableTest extends BcTestCase
{

    /**
     * リビジョンの保存と取得（エンティティが CakePHP 5.2 で成立すること）
     */
    public function testSaveAndFind(): void
    {
        $table = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        $entity = $table->newEntity([
            'model_name' => 'BaserCore.Pages',
            'model_id' => 1,
            'revision' => 1,
            'deta_object' => serialize(['dummy' => true]),
            'user_id' => 1,
        ]);
        $table->saveOrFail($entity);
        $saved = $table->find()
            ->where(['model_name' => 'BaserCore.Pages', 'model_id' => 1])
            ->first();
        $this->assertNotNull($saved, 'リビジョンが保存されていない');
        $this->assertSame(1, $saved->revision);
        $this->assertNotNull($saved->created, 'Timestamp behavior が効いていない');
    }

}
