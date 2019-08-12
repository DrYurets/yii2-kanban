<?php
/**
 * @package yii2-kanban
 * @author Simon Karlen <simi.albi@outlook.com>
 * @copyright Copyright © 2019 Simon Karlen
 */

namespace simialbi\yii2\kanban\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Class Link
 * @package simialbi\yii2\kanban\models
 *
 * @property integer $id
 * @property integer $task_id
 * @property string $url
 * @property integer $created_by
 * @property integer $updated_by
 * @property integer|string $created_at
 * @property integer|string $updated_at
 */
class Link extends ActiveRecord
{
    /**
     * {@inheritDoc}
     */
    public static function tableName()
    {
        return '{{%kanban_link}}';
    }

    /**
     * {@inheritDoc}
     */
    public function rules()
    {
        return [
            [['id', 'task_id'], 'integer'],
            ['url', 'url', 'enableIDN' => true],

            [['task_id', 'url'], 'required']
        ];
    }


    /**
     * {@inheritDoc}
     */
    public function behaviors()
    {
        return [
            'blameable' => [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    self::EVENT_BEFORE_INSERT => ['created_by', 'updated_by'],
                    self::EVENT_BEFORE_UPDATE => 'updated_by'
                ]
            ],
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    self::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    self::EVENT_BEFORE_UPDATE => 'updated_at'
                ]
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('simialbi/kanban/model/link', 'Id'),
            'task_id' => Yii::t('simialbi/kanban/model/link', 'Task'),
            'url' => Yii::t('simialbi/kanban/model/link', 'URL'),
            'created_by' => Yii::t('simialbi/kanban/model/link', 'Created by'),
            'updated_by' => Yii::t('simialbi/kanban/model/link', 'Updated by'),
            'created_at' => Yii::t('simialbi/kanban/model/link', 'Created at'),
            'updated_at' => Yii::t('simialbi/kanban/model/link', 'Updated at'),
        ];
    }
}