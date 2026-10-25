<?php
class RevisionControlHelperEventListener extends BcHelperEventListener {
/**
 * 登録イベント
 *
 * @var array
 */
	public $events = array(
		'Form.afterEnd'
	);


	public function formAfterEnd(CakeEvent $event) {
		$view = $event->subject;

		foreach(Configure::read('RevisionControl.views') as $modelName => $requestTarget) {
			if ($requestTarget['controller'] == $view->request['controller']
				&& $requestTarget['action'] == $view->request['action']
			) {

				$revisionControlMdl = ClassRegistry::init('RevisionControl.RevisionControl');
				$id = $view->request->data[$modelName]['id'];

				// 過去リヴィジョンのデータを取得
				$revList = $revisionControlMdl->find('all', array(
					'conditions' => array(
						'model_name' => $modelName,
						'model_id' => $id,
					),
					'order' => 'revision desc'
				));

				if ($revList) {
					echo $view->element('RevisionControl.admin/revision_control_list', array('revList' => $revList));
				}
			}
		}
	}
}
