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

		foreach(Configure::read('RevisionControl.excludeFormId') as $excludeId) {
			if (isset($event->data['id']) && $event->data['id'] == $excludeId) {
				return;
			}
		}

		foreach(Configure::read('RevisionControl.views') as $modelName => $requestTarget) {
			if ($requestTarget['controller'] == $view->request['controller']
				&& $requestTarget['action'] == $view->request['action']
			) {

				$revisionControlMdl = ClassRegistry::init('RevisionControl.RevisionControl');
				$id = $view->request->data[$modelName]['id'];

				// 過去リヴィジョンのデータを取得
				$revisionControlMdl->bindModel([
					'belongsTo' => [
						'User' => [
							'className' => 'User',
							'foreignKey' => 'user_id',
						]
					]
				]);
				$revList = $revisionControlMdl->find('all', [
					'conditions' => [
						'RevisionControl.model_name' => $modelName,
						'RevisionControl.model_id' => $id,
					],
					'fields' => [
						'RevisionControl.id',
						'RevisionControl.created',
						'RevisionControl.modified',
						'RevisionControl.model_name',
						'RevisionControl.model_id',
						'RevisionControl.revision',
						'RevisionControl.user_id',
						'User.id',
						'User.real_name_1',
						'User.real_name_2',
					],
					'order' => 'revision desc',
					'limit' => Configure::read('RevisionControl.displayLimit')
				]);

				if ($revList) {
					echo $view->element('RevisionControl.admin/rivision_control_list', array('revList' => $revList));
				}
			}
		}
	}
}
