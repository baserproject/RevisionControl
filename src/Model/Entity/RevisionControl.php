<?php
declare(strict_types=1);

namespace RevisionControl\Model\Entity;

use Cake\ORM\Entity;

/**
 * RevisionControl Entity
 *
 * @property int $id
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 * @property string|null $model_name
 * @property int|null $model_id
 * @property int|null $revision
 * @property text|null $deta_object
 * @property int|null $user_id
 */
class RevisionControl extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        '*' => true,
        'id' => false
    ];
}
