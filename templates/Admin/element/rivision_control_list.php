<?php
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\Routing\Router;
/**
 * @var array $revList
 */
$requestParams = $this->getRequest()->getAttribute('params');
?>
<div class="RevisionControlList">
    <h2 class="bca-main__heading" data-bca-heading-size="lg">リビジョン情報</h2>
    <ul class="clear bca-update-log__list">
        <?php foreach($revList as $data): ?>
            <?php /**/
            $urlParams = array(
                'controller' => $requestParams['controller'],
                'action' => $requestParams['action'],
            );
            if (!empty($requestParams['pass'])) {
                $urlParams += $requestParams['pass'];
            }
            if (!empty($requestParams['named'])) {
                $urlParams +=$requestParams['named'];
            }
            $urlParams['rev'] = $data->revision;
            $url = \Cake\Routing\Router::url($urlParams);
            // baserCMS 5.0.x系のRouter::urlでは、末尾のrevがつかないため、追加する
            if (strpos($url, '/rev:') === false) {
                $url .= '/rev:'. $data->revision;
            }
            ?>
            <li class="bca-update-log__list-item">
                <a href="<?php echo $url; ?>" onclick="return confirm('過去のリビジョン情報で編集を開きますか？')">
                    <?php echo h($data->revision); ?>:
                    <?php echo date("Y.m.d H:i:s", strtotime($data->created)) ?>
					 :
					[<?php echo h($data->Users['id']); ?>]
                    <?php echo h($data->Users['real_name_1']); ?>
                    <?php if ($data->Users['real_name_2']) echo h($data->Users['real_name_2']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
