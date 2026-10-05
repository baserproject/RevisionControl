<?php
namespace RevisionControl\Event;

use BaserCore\Event\BcModelEventListener;
use BaserCore\Utility\BcUtil;
use BaserCore\Utility\BcFolder;
use Cake\Event\Event;
use Cake\Utility\Inflector;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;


/**
 * [RevisionControl]
 *
 */
class RevisionControlModelEventListener extends BcModelEventListener {
/**
 * 登録イベント
 *
 * @var array
 */
	public $events = array(
		// アイキャッチのファイル名は BcUploadBehavior::afterSave()（Behavior は
		// EventManager::$defaultPriority = 10 で登録される）で確定するため、それより後に走らせる。
		// 優先度を明示しないと BaserCorePlugin::loadPlugin() が plugins.priority（有効化順・
		// 管理画面で並べ替え可能）を優先度として登録するため、10 未満になると Behavior より
		// 先に走って null を記録してしまう。値は registerPluginEvent() の既定値に合わせる。
		'afterSave' => ['priority' => 100],
		'RevisionControl.beforeFind'
	);

/**
 * afterSave
 *
 * @param Event $event
 * @return void
 */
	public function afterSave(Event $event) {

		$model = $event->getSubject();
		$entity = $event->getData('entity');
		$modelName = $entity->getSource(); // entityからModel名を取得
		$modelId = $entity->id;
		$revision = null;
		$limit = null;
		$revisionControlSetting = Configure::read('RevisionControl'); // プラグイン設定読み込み

		if (array_key_exists($modelName, $revisionControlSetting['models']) && $modelId) {
			// ページの場合 entity_id, ブログ記事なら blog_post_id
			if (isset($entity->type) && $entity->type == 'Page') {
				$modelId = $entity->entity_id;
			}
			$limit   = $revisionControlSetting['limit'];
			$actsAs  = $revisionControlSetting['actsAs'];
			$bkDir   = $revisionControlSetting['filesDir'];
			$revisionControlMdl = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');

			// 最新リビジョン番号を取得
			$query = $revisionControlMdl->find()
				->where([
					'model_name' => $modelName,
					'model_id' => $modelId
				])
				->orderBy(['revision' => 'DESC']);
			$prevData = $query->first();
			// BlogPostsはsaveが２回走るため、1秒以内のレコードは保存しない
			if  (!empty($prevData) && strval($prevData->get('modified')) == strval($entity->get('modified'))) {
				return;
			}
			if (isset($prevData->revision)) {
				$revision = intval($prevData->revision) + 1;
			} else {
				$revision = 1;
			}

			// タイムスタンプデータを削除
			$revData = array(
				'RevisionControl' => array(
					'model_name' => $modelName,
					'model_id' => $modelId,
					'revision' => $revision,
					'deta_object' => serialize($entity)
				)
			);

			// 更新ユーザ情報を追加
			$user = BcUtil::loginUser();
			if ($user) {
				$revData['RevisionControl']['user_id'] = $user['id'];
			}
			// 保存
      $saveEntity = $revisionControlMdl->newEntity($revData);
      $saveEntity->model_name = $modelName;
      $saveEntity->model_id = $modelId;
      $saveEntity->revision = $revision;
      $saveEntity->deta_object = serialize($entity);
			if ($user) $saveEntity->user_id = $user['id'];
      $revisionControlMdl->saveOrFail($saveEntity);

			//\Cake\Log\Log::write('error', $model->behaviors());

			// BcUpload関連のデータを複製
			// BcUploadBehavior内では、BcBlog.BlogPostsではなくBlogPostsで格納されているため、変数をモデル名だけにする
			$modelArray = explode('.', $modelName);
			$modelOnlyName = $modelArray[count($modelArray)-1];

			if (!empty($model->behaviors()->BcUpload) && !empty($model->getBehavior('BcUpload')) && !empty($actsAs['BcUpload'][$modelOnlyName])) {
				foreach ($model->getBehavior('BcUpload')->BcFileUploader as $columnName => $fieldParams) {
					if (array_key_exists($columnName, $actsAs['BcUpload'])) {
						foreach ($actsAs['BcUpload'][$columnName] as $field) {
							//var_dump($entity->get($field));
							// 個別処理
							if ($modelName == "BcBlog.BlogPosts") {
								$contentId = $entity->blog_content_id;
								// 未設定時は null。文字列化して扱う（連結結果は同一・preg_replace への null 引き渡しを避ける）
								$fieldValue = (string)$entity->get($field);
								$orgFilePath = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS . $fieldValue;
								$bkFilePath = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS . $bkDir . DS . $saveEntity->id . DS . $fieldValue;

								(new BcFolder(dirname(WWW_ROOT . $bkFilePath)))->create();
								// is_file: アイキャッチ未設定だとパスがディレクトリに解決される（旧 File::exists() 同等）
								if (is_file(WWW_ROOT . $orgFilePath)) {
									copy(WWW_ROOT . $orgFilePath, WWW_ROOT . $bkFilePath);
								}

								// thumbファイル ( __mobile_thumb /  __thumb )
								$orgFilePathThumb1 = preg_replace("/\.([^.]+)$/", "__mobile_thumb.$1", $orgFilePath);
								if (file_exists($orgFilePathThumb1)) {
									$bkFilePathThumb1 = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS .
										$bkDir . DS . $saveEntity->id . DS .
										preg_replace("/\.([^.]+)$/", "__mobile_thumb.$1", $fieldValue);
									if (is_file(WWW_ROOT . $orgFilePathThumb1)) {
										copy(WWW_ROOT . $orgFilePathThumb1, WWW_ROOT . $bkFilePathThumb1);
									}
								}
								$orgFilePathThumb2 = preg_replace("/\.([^.]+)$/", "__thumb.$1", $orgFilePath);
								if (file_exists($orgFilePathThumb2)) {
									$bkFilePathThumb2 = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS .
										$bkDir . DS . $saveEntity->id . DS .
										preg_replace("/\.([^.]+)$/", "__thumb.$1", $fieldValue);
									if (is_file(WWW_ROOT . $orgFilePathThumb2)) {
										copy(WWW_ROOT . $orgFilePathThumb2, WWW_ROOT . $bkFilePathThumb2);
									}
								}
							}


						}
					}
				}
			}

			// リビジョン制限オーバーデータの削除
			if ($limit) {
				$revisionListQuery = $revisionControlMdl->find()
					->where([
						'model_name' => $modelName,
						'model_id' => $modelId
					])
					->orderBy(['revision' => 'DESC']);
				$revisionList = $revisionListQuery->all()->toList();
				$i = 0;
				foreach($revisionList as $data) {
					if (++$i > $limit) {
						//$revisionControlMdl->delete(intval($data->id));
						$entity = $revisionControlMdl->get($data->id);
						$result = $revisionControlMdl->delete($entity);
						// BlogPostEyeCatch関連データ削除
						if ($data->model_name == 'BcBlog.BlogPosts') {
							$deta_object = preg_replace_callback('!s:(\d+):"([\s\S]*?)";!', function($m) {
								return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
							}, $data->deta_object);
							$dataObj = unserialize($deta_object);
							$revBkPath = WWW_ROOT . 'files' . DS . 'blog' . DS .
								$dataObj->blog_content_id . DS . 'blog_posts' . DS .
								$bkDir . DS . intval($data->id);
							(new BcFolder($revBkPath))->delete();
						}
					}
				}
			}
		}

	}
	    /**
     * bindModel共通処理
     *
     * @param Event $event
     */
    public function revisionControlBeforeFind(Event $event)
    {
        if (is_object($event)) {

            // 呼び出し元のモデルを取得
            $Model = $event->getSubject();

            // モデル名は単数形のキャメルケースでDBに登録されている（4系のなごり）
            $modelName = Inflector::camelize(Inflector::singularize($Model->getAlias()));
        }
    }

}
