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

use BaserCore\Test\Scenario\InitAppScenario;
use BaserCore\TestSuite\BcTestCase;
use BaserCore\Utility\BcEvent;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use Cake\View\View;
use CakephpFixtureFactories\Scenario\ScenarioAwareTrait;

/**
 * RevisionControlHelperEventListenerTest
 *
 * フォーム末尾（Form.afterEnd）のリビジョン一覧表示を、本番と同じ
 * グローバル EventManager 経由の dispatch で検証する。
 */
class RevisionControlHelperEventListenerTest extends BcTestCase
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
     * リビジョンを作成する
     */
    private function createRevisions(int $modelId, int $count): void
    {
        $table = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        for ($i = 1; $i <= $count; $i++) {
            $table->saveOrFail($table->newEntity([
                'model_name' => 'BaserCore.Pages',
                'model_id' => $modelId,
                'revision' => $i,
                'deta_object' => serialize(['rev' => $i]),
                'user_id' => 1,
            ]));
        }
    }

    /**
     * 管理画面の固定ページ編集画面相当の View を作る
     */
    private function createAdminEditView(int $pageId): View
    {
        $request = $this->getRequest('/baser/admin/baser-core/pages/edit/' . $pageId);
        $view = new View($request);
        $view->set('page', new \Cake\ORM\Entity(['id' => $pageId]));
        return $view;
    }

    /**
     * フォーム末尾にリビジョン一覧が表示される
     */
    public function testFormAfterEndShowsRevisionList(): void
    {
        $this->createRevisions(5, 2);
        $view = $this->createAdminEditView(5);

        $html = '';
        ob_start();
        EventManager::instance()->dispatch(
            new Event('Helper.Form.afterEnd', $view, ['id' => 'PageAdminEditForm', 'out' => '</form>'])
        );
        $html = ob_get_clean();

        $this->assertStringContainsString('リビジョン情報', $html, 'リビジョン一覧が表示されていない');
        $user = TableRegistry::getTableLocator()->get('BaserCore.Users')->get(1);
        $this->assertStringContainsString(h($user->real_name_1), $html, '更新ユーザー名が表示されていない');
        // リンクはパス引数（pass）として rev:N を含む形式であること（クエリ文字列だと閲覧側で解釈されない）
        $this->assertMatchesRegularExpression(
            '!href="[^"?]*/rev:2"!',
            $html,
            'rev:N がクエリ文字列ではなくパス末尾に付いていない'
        );
    }

    /**
     * 除外フォームIDではリビジョン一覧を表示しない
     *
     * （お気に入り等の別フォームが同一画面で end() されたときに
     * 一覧が重複表示されるのを防ぐ設定 excludeFormId の検証）
     */
    public function testFormAfterEndSkipsExcludedFormId(): void
    {
        $this->createRevisions(5, 1);
        $view = $this->createAdminEditView(5);

        $html = '';
        ob_start();
        EventManager::instance()->dispatch(
            new Event('Helper.Form.afterEnd', $view, ['id' => 'FavoriteAjaxForm', 'out' => '</form>'])
        );
        $html = ob_get_clean();

        $this->assertSame('', $html, '除外フォームIDなのにリビジョン一覧が表示されている');
    }

    /**
     * 管理画面以外（フロント）では何も出力しない
     */
    public function testFormAfterEndDoesNothingOnFront(): void
    {
        $this->createRevisions(5, 1);
        $request = $this->getRequest('/');
        $view = new View($request);

        $html = '';
        ob_start();
        EventManager::instance()->dispatch(
            new Event('Helper.Form.afterEnd', $view, ['id' => 'SomeFrontForm', 'out' => '</form>'])
        );
        $html = ob_get_clean();

        $this->assertSame('', $html, 'フロントでリビジョン一覧が出力されている');
    }

}
