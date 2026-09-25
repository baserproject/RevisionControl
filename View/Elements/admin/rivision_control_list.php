<div class="RevisionControlList">
    <div class="bca-collapse__action">
        <button type="button" class="bca-collapse__btn" data-bca-collapse="collapse"
                data-bca-target="#revisionHistoryBody" aria-expanded="false"
                aria-controls="revisionHistoryBody">更新履歴&nbsp;&nbsp;<i
                class="bca-icon--chevron-down bca-collapse__btn-icon"></i></button>
    </div>
    <div class="bca-collapse" id="revisionHistoryBody" data-bca-state="">
    <ul>
        <?php foreach($revList as $data): ?>
            <?php
            $urlParams = array(
                'controller' => $this->request['controller'],
                'action' => $this->request['action'],
            );
            if ($this->request['pass']) {
                $urlParams +=$this->request['pass'];
            }
            if ($this->request['named']) {
                $urlParams +=$this->request['named'];
            }
            $urlParams['rev'] = $data['RevisionControl']['revision'];
            ?>
            <li>
                <a href="<?php echo Router::url($urlParams ); ?>" onclick="return confirm('過去の更新履歴で編集を開きますか？')">
                    <?php echo date("Y.m.d H:i:s", strtotime($data['RevisionControl']['created'])) ?>
                    (<?php echo $data['RevisionControl']['revision']; ?>)
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    </div>
</div>