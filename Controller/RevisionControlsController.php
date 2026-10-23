<?php
class RevisionControlsController extends AppController {
	public $uses = ['RevisionControl.RevisionControl'];
	public $components = ['BcAuth', 'Cookie', 'BcAuthConfigure'];

	/**
	 * [ADMIN] 更新履歴を1件削除する
	 *
	 * システム管理・マネージャーのみ許可する
	 *
	 * @param int $id revision_controls.id
	 * @return void
	 */
	public function admin_delete($id = null) {
		# POSTとトークンを確認する
		$this->_checkSubmitToken();
		# システム管理・マネージャーのみ許可する (直リンク叩きされた場合に403エラーを返す)
		if(!onemindUtil::availableEditUser()) {
			throw new ForbiddenException();
		}
		// 戻り先のURLを取得する
		$redirect = preg_replace('/\/rev:\d+/', '', $this->referer());
		// 削除対象のデータが存在するかをチェックする
		if (!$id || !$this->RevisionControl->exists($id)) {
			$this->BcMessage->setError(__d('baser', '無効な処理です。'));
			$this->redirect($redirect);
		}
		if ($this->RevisionControl->delete($id)) {
			$this->BcMessage->setSuccess(__d('baser', '更新履歴を削除しました。'));
		} else {
			$this->BcMessage->setError(__d('baser', '更新履歴の削除に失敗しました。'));
		}
		$this->redirect($redirect);

	}
}
