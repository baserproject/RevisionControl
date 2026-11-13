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
use BaserCore\TestSuite\BcTestCase;
use BaserCore\Utility\BcEvent;
use BaserCore\Utility\BcFolder;
use Cake\ORM\TableRegistry;

/**
 * RevisionControlModelEventListenerTest
 *
 * 本プラグインの本体機能（保存時のリビジョン記録）は Model.afterSave の
 * グローバルイベントで動くため、本番の登録経路（BcEvent::registerPluginEvent）を
 * setUp で再現してから対象モデルを保存して検証する。
 */
class RevisionControlModelEventListenerTest extends BcTestCase
{

    /**
     * set up
     */
    public function setUp(): void
    {
        parent::setUp();
        // BcTestCase::setUp() が EventManager をリセットするため、
        // 本番（BaserCorePlugin::loadPlugin）と同じ経路でプラグインイベントを登録する。
        // 優先度は本番と同じく plugins.priority が渡る（既定の 100 では
        // Behavior（既定 10）との前後関係が本番と逆になり不具合を再現できない）
        BcEvent::registerPluginEvent('RevisionControl', 9);
    }

    /**
     * tear down
     */
    public function tearDown(): void
    {
        $blogFilesDir = WWW_ROOT . 'files' . DS . 'blog';
        if (is_dir($blogFilesDir)) {
            (new BcFolder($blogFilesDir))->delete();
        }
        parent::tearDown();
    }

    /**
     * 固定ページの保存でリビジョンが記録される（連続保存で revision が増える）
     */
    public function testAfterSaveCreatesRevisionForPage(): void
    {
        $revisions = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        $pages = TableRegistry::getTableLocator()->get('BaserCore.Pages');

        $page = PageFactory::make(['contents' => '<p>rev1</p>'])->persist();

        $saved = $revisions->find()
            ->where(['model_name' => 'BaserCore.Pages', 'model_id' => $page->id])
            ->orderBy(['revision' => 'ASC'])
            ->all()->toList();
        $this->assertCount(1, $saved, '保存でリビジョンが記録されていない');
        $this->assertSame(1, $saved[0]->revision);
        $entityInRevision = unserialize($saved[0]->deta_object);
        $this->assertSame('<p>rev1</p>', $entityInRevision->contents, 'リビジョンに保存時点のデータが入っていない');

        // 2回目の保存（編集）で revision=2 が積まれる
        // 前回保存との modified 同値ガード（1秒以内の二重保存抑止）があるため modified を進める
        $entity = $pages->get($page->id);
        $entity->contents = '<p>rev2</p>';
        $entity->modified = new \Cake\I18n\DateTime('+1 minute');
        $entity->setDirty('modified', true);
        $pages->saveOrFail($entity);

        $saved = $revisions->find()
            ->where(['model_name' => 'BaserCore.Pages', 'model_id' => $page->id])
            ->orderBy(['revision' => 'ASC'])
            ->all()->toList();
        $this->assertCount(2, $saved, '編集でリビジョンが積まれていない');
        $this->assertSame(2, $saved[1]->revision);
    }

    /**
     * 退避元が実ファイルでない場合は、黙って見送りリビジョンだけ記録する
     *
     * ケース A（未設定＝null）: 退避元パスが `.../blog_posts/`（ディレクトリ）に解決される。
     * 存在判定が file_exists() だとディレクトリでも真になり、copy() が
     * 「first argument cannot be a directory」を出す。dev-5 の `Cake\Filesystem\File::copy()` は
     * exists() が is_file() まで見て黙って見送っていた（＝忠実移行の基準）。
     * あわせて preg_replace() への null 引き渡し（PHP 8.5 非推奨）も塞ぐ。
     * ケース B（値はあるが実ファイルが無い）: 4 系からファイル未移行の状態など。
     *
     * 退避元サムネの存在判定は相対パス（`basercms53-migration` 台帳の懸案 6）のため、
     * 本番（webroot/index.php）と同じくカレントディレクトリを webroot にしないと再現しない。
     */
    public function testAfterSaveSkipsEyeCatchCopyWhenSourceIsNotAFile(): void
    {
        \BcBlog\Test\Factory\BlogContentFactory::make(['id' => 13])->persist();

        $errors = [];
        $cwd = getcwd();
        chdir(WWW_ROOT);
        set_error_handler(function ($errno, $errstr, $errfile = '', $errline = 0) use (&$errors) {
            if (str_contains($errfile, 'RevisionControlModelEventListener')) {
                $errors[] = $errstr . ' @' . $errline;
            }
            return true;
        });
        try {
            $posts = [
                'A:未設定' => \BcBlog\Test\Factory\BlogPostFactory::make([
                    'blog_content_id' => 13, 'eye_catch' => null,
                ])->persist(),
                'B:実ファイル無し' => \BcBlog\Test\Factory\BlogPostFactory::make([
                    'blog_content_id' => 13, 'eye_catch' => 'missing.png',
                ])->persist(),
            ];
        } finally {
            restore_error_handler();
            chdir($cwd);
        }

        $this->assertSame([], $errors, '退避元が実ファイルでない保存で PHP 警告／非推奨が発生している');

        $revisions = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        foreach($posts as $case => $post) {
            $saved = $revisions->find()
                ->where(['model_name' => 'BcBlog.BlogPosts', 'model_id' => $post->id])
                ->all()->toList();
            $this->assertCount(1, $saved, "{$case}: 退避できなくてもリビジョンは記録される");
        }
    }

    /**
     * アイキャッチが設定済みの編集保存では、実ファイルがリビジョン退避ディレクトリに複製される
     *
     * 退避元の存在判定を is_file() に変えても、正常系（複製元が実在するファイル）が
     * これまでどおり複製されることを担保する。
     */
    public function testAfterSaveCopiesEyeCatchToRevisionDir(): void
    {
        \BcBlog\Test\Factory\BlogContentFactory::make(['id' => 14])->persist();
        $posts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $revisions = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');

        $post = \BcBlog\Test\Factory\BlogPostFactory::make([
            'blog_content_id' => 14,
            'eye_catch' => '2026/08/00000001_eye_catch.png',
        ])->persist();

        // 退避元の実ファイルを用意する
        $orgDir = WWW_ROOT . 'files' . DS . 'blog' . DS . '14' . DS . 'blog_posts' . DS . '2026' . DS . '08';
        (new BcFolder($orgDir))->create();
        file_put_contents($orgDir . DS . '00000001_eye_catch.png', 'dummy');

        $entity = $posts->get($post->id);
        $entity->modified = new \Cake\I18n\DateTime('+1 minute');
        $entity->setDirty('modified', true);
        $posts->saveOrFail($entity);

        $latest = $revisions->find()
            ->where(['model_name' => 'BcBlog.BlogPosts', 'model_id' => $post->id])
            ->orderBy(['revision' => 'DESC'])
            ->first();
        $this->assertNotNull($latest, '編集保存でリビジョンが記録されていない');
        $this->assertFileExists(
            WWW_ROOT . 'files' . DS . 'blog' . DS . '14' . DS . 'blog_posts' . DS . '_rvc' . DS .
                $latest->id . DS . '2026' . DS . '08' . DS . '00000001_eye_catch.png',
            'アイキャッチがリビジョン退避ディレクトリに複製されていない'
        );
    }

    /**
     * アイキャッチを同じ保存でアップロードした場合も、リビジョンに記録され退避される
     *
     * `BcUploadBehavior::afterSave()`（`BcFileUploader::saveFiles()`）が実行されて初めて
     * エンティティの `eye_catch` に確定ファイル名が入る。プラグインのイベントは
     * `BaserCorePlugin::loadPlugin()` が `plugins.priority`（本サイトは 9）を優先度として
     * 登録するため、Behavior の既定優先度 10 より先に走ってしまい、
     * リビジョンには常に null が記録されて退避も行われなかった。
     */
    public function testAfterSaveRecordsEyeCatchUploadedInSameSave(): void
    {
        \BcBlog\Test\Factory\BlogContentFactory::make(['id' => 15])->persist();
        $posts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $revisions = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        $post = \BcBlog\Test\Factory\BlogPostFactory::make(['blog_content_id' => 15, 'no' => 1])->persist();
        // 本番（BlogPostsController::beforeFilter → BlogPostsService::setupUpload）と同じく
        // 保存先ディレクトリ（blog/{blog_content_id}/blog_posts）を設定する
        $posts->setupUpload(15);

        // 管理画面のフォーム送信（アイキャッチのアップロード）を模す
        $tmpFile = TMP . 'revision_control_eye_catch.png';
        $image = imagecreatetruecolor(20, 20);
        imagepng($image, $tmpFile);
        $uploaded = new \Laminas\Diactoros\UploadedFile(
            $tmpFile, filesize($tmpFile), UPLOAD_ERR_OK, 'eye_catch.png', 'image/png'
        );

        $entity = $posts->patchEntity($posts->get($post->id), [
            'title' => 'アイキャッチあり',
            'eye_catch' => $uploaded,
        ]);
        // 直前のリビジョンとの modified 同値ガード（1 秒以内の二重保存抑止）を避ける
        $entity->modified = new \Cake\I18n\DateTime('+5 minutes');
        $entity->setDirty('modified', true);
        $posts->saveOrFail($entity);

        $eyeCatch = $posts->get($post->id)->eye_catch;
        $this->assertNotEmpty($eyeCatch, '前提: 保存でアイキャッチが登録されていない');

        $latest = $revisions->find()
            ->where(['model_name' => 'BcBlog.BlogPosts', 'model_id' => $post->id])
            ->orderBy(['revision' => 'DESC'])
            ->first();
        $dataObj = unserialize($latest->deta_object);
        $this->assertSame($eyeCatch, $dataObj->eye_catch, 'リビジョンにアイキャッチのファイル名が記録されていない');
        $this->assertFileExists(
            WWW_ROOT . 'files' . DS . 'blog' . DS . '15' . DS . 'blog_posts' . DS . '_rvc' . DS .
                $latest->id . DS . $eyeCatch,
            'アイキャッチがリビジョン退避ディレクトリに複製されていない'
        );
    }

    /**
     * 世代制限（limit）を超えた古いリビジョンが削除される
     */
    public function testAfterSaveDeletesRevisionsOverLimit(): void
    {
        \Cake\Core\Configure::write('RevisionControl.limit', 2);
        $revisions = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        $pages = TableRegistry::getTableLocator()->get('BaserCore.Pages');

        $page = PageFactory::make(['contents' => '<p>r1</p>'])->persist();
        foreach ([2, 3] as $i) {
            $entity = $pages->get($page->id);
            $entity->contents = "<p>r{$i}</p>";
            $entity->modified = new \Cake\I18n\DateTime("+{$i} minutes");
            $entity->setDirty('modified', true);
            $pages->saveOrFail($entity);
        }

        $list = $revisions->find()
            ->where(['model_name' => 'BaserCore.Pages', 'model_id' => $page->id])
            ->orderBy(['revision' => 'ASC'])
            ->all()->toList();
        $this->assertCount(2, $list, '世代制限を超えたリビジョンが削除されていない');
        $this->assertSame([2, 3], [$list[0]->revision, $list[1]->revision], '最古のリビジョンから削除されていない');
    }

    /**
     * ブログ記事でも世代制限の削除が動く（アイキャッチ退避ディレクトリの削除分岐を通す）
     */
    public function testAfterSaveDeletesBlogPostRevisionsOverLimit(): void
    {
        \Cake\Core\Configure::write('RevisionControl.limit', 1);
        $revisions = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
        $posts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        \BcBlog\Test\Factory\BlogContentFactory::make(['id' => 12])->persist();

        $post = \BcBlog\Test\Factory\BlogPostFactory::make([
            'blog_content_id' => 12,
            'eye_catch' => 'eyecatch.png',
        ])->persist();
        $entity = $posts->get($post->id);
        $entity->content = '<p>updated</p>';
        $entity->modified = new \Cake\I18n\DateTime('+2 minutes');
        $entity->setDirty('modified', true);
        $posts->saveOrFail($entity);

        $list = $revisions->find()
            ->where(['model_name' => 'BcBlog.BlogPosts', 'model_id' => $post->id])
            ->all()->toList();
        $this->assertCount(1, $list, 'ブログ記事の世代制限削除が動いていない');
        $this->assertSame(2, $list[0]->revision, '最新リビジョンが残っていない');
    }

}
