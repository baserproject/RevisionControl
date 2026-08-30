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

namespace RevisionControl\Test\TestCase\Event;

use BaserCore\Test\Factory\PageFactory;
use BaserCore\Test\Scenario\InitAppScenario;
use BaserCore\TestSuite\BcTestCase;
use BaserCore\Utility\BcEvent;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use Cake\View\View;
use CakephpFixtureFactories\Scenario\ScenarioAwareTrait;

/**
 * RevisionControlViewEventListenerTest
 *
 * 編集画面 URL 末尾の rev:N 指定で、過去リビジョンのデータが
 * ビュー変数へマウントされることを検証する。
 */
class RevisionControlViewEventListenerTest extends BcTestCase
{

    use ScenarioAwareTrait;

    /**
     * set up
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->loadFixtureScenario(InitAppScenario::class);
        BcEvent::registerPluginEvent('RevisionControl');
    }

    /**
     * rev:N 指定で過去リビジョンのデータがビュー変数にマウントされる
     */
    public function testBeforeRenderMountsOldRevision(): void
    {
        // 保存2回でリビジョン 1, 2 を作る（afterSave リスナー経由の実データ）
        $pages = TableRegistry::getTableLocator()->get('BaserCore.Pages');
        $page = PageFactory::make(['contents' => '<p>rev1</p>'])->persist();
        $entity = $pages->get($page->id);
        $entity->contents = '<p>rev2</p>';
        $entity->modified = new \Cake\I18n\DateTime('+1 minute');
        $entity->setDirty('modified', true);
        $pages->saveOrFail($entity);

        // rev:1 を指定した編集画面リクエスト
        $request = $this->getRequest('/baser/admin/baser-core/pages/edit/' . $page->id . '/rev:1');
        $view = new View($request);
        $view->set('page', $pages->get($page->id));

        EventManager::instance()->dispatch(new Event('View.beforeRender', $view));

        $mounted = $view->get('page');
        $this->assertSame('<p>rev1</p>', $mounted->contents, '過去リビジョンのデータがマウントされていない');
    }

    /**
     * rev 指定なしではビュー変数を差し替えない
     */
    public function testBeforeRenderKeepsCurrentDataWithoutRev(): void
    {
        $pages = TableRegistry::getTableLocator()->get('BaserCore.Pages');
        $page = PageFactory::make(['contents' => '<p>rev1</p>'])->persist();

        $request = $this->getRequest('/baser/admin/baser-core/pages/edit/' . $page->id);
        $view = new View($request);
        $current = $pages->get($page->id);
        $view->set('page', $current);

        EventManager::instance()->dispatch(new Event('View.beforeRender', $view));

        $this->assertSame($current, $view->get('page'), 'rev 指定なしなのにビュー変数が差し替えられた');
    }

}
