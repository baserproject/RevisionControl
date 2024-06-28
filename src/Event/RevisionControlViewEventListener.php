<?php
namespace RevisionControl\Event;

use BaserCore\Event\BcViewEventListener;
use Cake\View\Helper\HtmlHelper;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\View\View;
/**
 * [RevisionControl]
 *
 */
class RevisionControlViewEventListener extends BcViewEventListener {
/**
 * 登録イベント
 *
 * @var array
 */
public $events = ['beforeRender'];

	/**
	 * beforeRender
	 *
	 * @param CakeEvent $event
	 * @return boolean
	 */
	public function beforeRender(Event $event)
	{
		$view = $event->getSubject();
		$request = $view->getRequest(); // requestの取得
		/* controller・actionなどはparamsに含まれるが、オブジェクトを取得できないため、getAttribute()を使う */

			foreach(Configure::read('RevisionControl.views') as $modelName => $requestTarget) {
				if ($requestTarget['controller'] == $request->getParam('controller') && $requestTarget['action'] == $request->getParam('action')) {
					// Rooterの仕組みが変わって['named']が取得できなくなったため、パスからリビジョン番号を取得
					$rev = 0;
					$pass = $request->getParam('pass');
					foreach ($pass as $value) {
						if (strpos($value, 'rev:')!== false) $rev = intval(str_replace('rev:', '', $value));
					}

					 // リビジョン番号がある場合のみ
					if($rev) {
						$revisionControlMdl = \Cake\ORM\TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
						$viewData = $view->get($requestTarget['data']); // データを取得 $view->get('page') or $view->get('post')
						if (!empty($viewData->id)) {
							$id = $viewData->id;
						} else { //データを取得しそこねた場合、パスからIDを取得
							$id = $request->getParam('controller') == 'Pages' ? $request->getParam('pass')[0] : $request->getParam('pass')[1];
						}
						$bkDir = Configure::read('RevisionControl.filesDir');
						// 過去リヴィジョンのデータを取得
						$query = $revisionControlMdl->find('all')
						->where([
							'RevisionControls.model_name' => $modelName,
							'RevisionControls.model_id' => $id,
							'RevisionControls.revision' => $rev,
						]);
						$data = $query->first();
						//var_dump($data);

						// 旧リヴィジョンデータのマウント
						if ($data) {
							$deta_object = preg_replace_callback('!s:(\d+):"([\s\S]*?)";!', function($m) {
									return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
								}, $data->deta_object);
							$dataObj = unserialize($deta_object);
							$overWriteModels = Configure::read('RevisionControl.models');
							$views = Configure::read('RevisionControl.views');
							foreach($overWriteModels[$modelName] as $overWriteModel) {
								if (!empty($views[$modelName]['data']) && $view->get($views[$modelName]['data'])) {
									// BcUpload処理
									if ($overWriteModel == 'BlogPosts' && Configure::read('RevisionControl.actsAs.BcUpload.' . $overWriteModel)) {
										$fileFields = Configure::read('RevisionControl.actsAs.BcUpload.' . $overWriteModel);
										foreach($fileFields as $fileField) {
											$fieldData = $dataObj->{$fileField};
											$revId = $data->id;
											if (empty($fieldData)) {
											} else {
												$path = "$bkDir/$revId/$fieldData";
												$dataObj->{$fileField} = $path;
											}
										}
									} // BcUpload処理ここまで
									// データのセット
									$view->set($views[$modelName]['data'] , $dataObj);
							}
						}
					}
				}//$paramsは存在しない

			}
		}
		return true;

	}


}
