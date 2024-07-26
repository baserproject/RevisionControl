<?php
namespace RevisionControl\Event;

use BaserCore\Event\BcHelperEventListener;
use BaserCore\Utility\BcUtil;
use BcBlog\View\Helper;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Utility\Hash;

/**
 * [RevisionControl]
 *
 */
class RevisionControlHelperEventListener extends BcHelperEventListener {
/**
 * 登録イベント
 *
 * @var array
 */
	public $events = [
		'Form.afterEnd'
	];


	/**
	 * formAfterEnd
	 * フォーム終了タグのあとに追加
	 *
	 * @param Event $event
	 * @return string
	 */
	public function formAfterEnd(Event $event) {

		if (!BcUtil::isAdminSystem()) {
			return $event->getData('out');
		}

		$view = $event->getSubject();

		foreach(Configure::read('RevisionControl.excludeFormId') as $excludeId) {
			if (isset($event->data['id']) && $event->data['id'] == $excludeId) {
				return;
			}
		}
    $request = $view->getRequest();
		//$attributes = $request->getAttributes();
		$controller = $request->getParam('controller');
		$action = $request->getParam('action');
		// ユーザーモデルを取得しておく
		$usersModel = TableRegistry::getTableLocator()->get('BaserCore.Users');
		foreach(Configure::read('RevisionControl.views') as $modelName => $requestTarget) {
			// views設定が現在の入力画面と一致した場合のみ動作
			if ($requestTarget['controller'] == $controller && $requestTarget['action'] == $action) {
				$data = $view->get($requestTarget['data']); // データを取得 $view->get('page') or $view->get('post')
				if (!empty($data->id)) {
					$id = $data->id;
				} else { //データを取得しそこねた場合、パスからIDを取得
					$id = $controller == 'Pages' ? $request->getParam('pass')[0] : $request->getParam('pass')[1];
				}
				// RevisionControlsテーブルを呼び出して find
				$revisionControlMdl = TableRegistry::getTableLocator()->get('RevisionControl.RevisionControls');
				$query = $revisionControlMdl->find('all',
					['order' => 'revision desc']
				)
				// ユーザーテーブルをjoinする。
				->join([
					'table' => $usersModel->getTable(), // ユーザーモデルからテーブル名取得
					'alias' => 'Users',
					'type' => 'LEFT',
					'conditions' => 'Users.id = RevisionControls.user_id',
				])
				->where([
						'RevisionControls.model_name' => $modelName,
						'RevisionControls.model_id' => $id,
				]);
				//$query->enableHydration(false); // エンティティーの代わりに配列を返す
				// 取得内容リスト
				$query->select([
						'RevisionControls.id',
						'RevisionControls.created',
						'RevisionControls.modified',
						'RevisionControls.model_name',
						'RevisionControls.model_id',
						'RevisionControls.revision',
						'RevisionControls.user_id',
						'Users.id',
						'Users.real_name_1',
						'Users.real_name_2',
					]);
				$revList = $query->all()->toList();
				// 編集画面で一度でも保存ボタンを押した場合のみリビジョン情報エレメントを表示
				if (!empty($revList)) {
					echo $view->element('RevisionControl.rivision_control_list', ['revList' => $revList]);
				}
			} //Configのviews 設定と合っていない入力画面はスルー
		}
	}
}
