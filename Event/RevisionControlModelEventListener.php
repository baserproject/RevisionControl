<?php
class RevisionControlModelEventListener extends BcModelEventListener {
/**
 * 登録イベント
 *
 * @var array
 */
	public $events = array(
		'afterSave',
	);

/**
 * afterSave
 *
 * @param CakeEvent $event
 * @return boolean
 */
	public function afterSave(CakeEvent $event) {

		$model = $event->subject;
		$modelName = $model->name;
		$modelId = null;
		$revision = null;
		$limit = null;

		if (array_key_exists($modelName, Configure::read('RevisionControl.models')) && $model->data[$modelName]['id']) {
			$modelId = $model->data[$modelName]['id'];
			$limit   = Configure::read('RevisionControl.limit');
			$actsAs  = Configure::read('RevisionControl.actsAs');
			$bkDir   = Configure::read('RevisionControl.filesDir');
			$revisionControlMdl = ClassRegistry::init('RevisionControl.RevisionControl');

			// 最新リビジョン番号を取得
			$prevData = $revisionControlMdl->find('first', array(
				'conditions' => array(
					'model_name' => $modelName,
					'model_id' => $modelId
				),
				'order' => 'revision desc',
			));

			if (isset($prevData['RevisionControl']['revision'])) {
				$revision = intval($prevData['RevisionControl']['revision']) + 1;
			} else {
				$revision = 1;
			}
			// タイムスタンプデータを削除
			$revData = array(
				'RevisionControl' => array(
					'model_name' => $modelName,
					'model_id' => $modelId,
					'revision' => $revision,
					'deta_object' => serialize($model->data)
				)
			);
			// 更新ユーザ情報を追加
			$user = BcUtil::loginUser();
			if ($user) {
				$revData['RevisionControl']['user_id'] = $user['id'];
			}
			// 保存
			$revisionControlMdl->save($revData, false);

			// BcUpload関連のデータを複製
			if (!empty($model->actsAs['BcUpload']['fields']) &&
				!empty($actsAs['BcUpload'][$modelName])) {
				foreach($model->actsAs['BcUpload']['fields'] as $columnName => $fieldParams) {
					if (in_array($columnName, $actsAs['BcUpload'][$modelName]) && !empty($model->data[$modelName][$columnName])) {
						// 個別処理
						if ($modelName == "BlogPost") {
							$contentId = $model->data[$modelName]['blog_content_id'];
							$orgFilePath = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS . $model->data[$modelName][$columnName];
							$bkFilePath = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS . $bkDir . DS . $revisionControlMdl->id . DS . $model->data[$modelName][$columnName];

							$dir = new Folder();
							$dir->create(dirname(WWW_ROOT . $bkFilePath), 0777);
							$file = new File(WWW_ROOT  . $orgFilePath);
							$file->copy(WWW_ROOT . $bkFilePath, true, 0777);

							// thumbファイル ( __mobile_thumb /  __thumb )
							$orgFilePathThumb1 = preg_replace("/\.([^.]+)$/", "__mobile_thumb.$1", $orgFilePath);
							if (file_exists($orgFilePathThumb1)) {
								$bkFilePathThumb1 = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS .
									$bkDir . DS . $revisionControlMdl->id . DS .
									preg_replace("/\.([^.]+)$/", "__mobile_thumb.$1", $model->data[$modelName][$columnName]);
								$file = new File(WWW_ROOT  . $orgFilePathThumb1);
								$file->copy(WWW_ROOT . $bkFilePathThumb1, true, 0777);
							}
							$orgFilePathThumb2 = preg_replace("/\.([^.]+)$/", "__thumb.$1", $orgFilePath);
							if (file_exists($orgFilePathThumb2)) {
								$bkFilePathThumb2 = 'files' . DS . 'blog' . DS . $contentId . DS . 'blog_posts' . DS .
									$bkDir . DS . $revisionControlMdl->id . DS .
									preg_replace("/\.([^.]+)$/", "__thumb.$1", $model->data[$modelName][$columnName]);
								$file = new File(WWW_ROOT  . $orgFilePathThumb2);
								$file->copy(WWW_ROOT . $bkFilePathThumb2, true, 0777);
							}
						}
					}
				}
			}

			// リビジョン制限オーバーデータの削除
			if ($limit) {
				$revisionList = $revisionControlMdl->find('all', array(
					'conditions' => array(
						'model_name' => $modelName,
						'model_id' => $modelId
					),
					'order' => 'revision desc',
				));
				$i = 0;
				foreach($revisionList as $data) {
					if (++$i > $limit) {
						$revisionControlMdl->delete(intval($data['RevisionControl']['id']));
						// BlogPostEyeCatch関連データ削除
						if ($data['RevisionControl']['model_name'] == 'BlogPost') {
							$dataObj = unserialize($data['RevisionControl']['deta_object']);
							$revBkPath = WWW_ROOT . 'files' . DS . 'blog' . DS .
								$dataObj['BlogPost']['blog_content_id'] . DS . 'blog_posts' . DS .
								$bkDir . DS . intval($data['RevisionControl']['id']);
							$dir = new Folder();
							$dir->delete($revBkPath);
						}
					}
				}
			}
		}
		return true;

	}
}
