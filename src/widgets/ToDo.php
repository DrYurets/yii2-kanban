<?php
/**
 * @package yii2-kanban
 * @author Simon Karlen <simi.albi@outlook.com>
 * @copyright Copyright © 2019 Simon Karlen
 */

namespace simialbi\yii2\kanban\widgets;

use kartik\select2\Select2;
use rmrevin\yii\fontawesome\FAR;
use rmrevin\yii\fontawesome\FAS;
use simialbi\yii2\kanban\KanbanAsset;
use simialbi\yii2\kanban\KanbanSwiperAsset;
use simialbi\yii2\kanban\models\Task;
use simialbi\yii2\turbo\Frame;
use simialbi\yii2\turbo\Modal;
use simialbi\yii2\widgets\Widget;
use Yii;
use yii\bootstrap4\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\JsExpression;

/**
 *
 * @package simialbi\yii2\kanban\widgets
 */
class ToDo extends Widget
{
    /**
     * {@inheritDoc}
     */
    public static $autoIdPrefix = 'sa-w';

    /**
     * @var array the HTML attributes (name-value pairs) for the container tag.
     * The values will be HTML-encoded using [[Html::encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     */
    public $options = [
        'class' => ['sa-todo']
    ];

    /**
     * @var array the HTML attributes (name-value pairs) for the items tags.
     * The values will be HTML-encoded using [[Html::encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     */
    public $listOptions = ['widget' => 'list-group'];

    /**
     * @var array the HTML attributes (name-value pairs) for the items tags.
     * The values will be HTML-encoded using [[Html::encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     */
    public $itemOptions = [
        'class' => ['widget' => 'list-group-item list-group-item-action', 'rounded-0', 'p-2', 'd-flex']
    ];

    /**
     * @var boolean Set this property to *true* to add a board filter on top
     * of the list.
     */
    public $addBoardFilter = false;

    /**
     * @var string Kanban module name
     */
    public $kanbanModuleName = 'kanban';

    /**
     * @var boolean Whether to render the modal or not
     */
    public $renderModal = true;

    /**
     * {@inheritDoc}
     * @throws \yii\base\InvalidConfigException
     */
    public function run()
    {
        $this->view->registerAssetBundle(KanbanSwiperAsset::class);
        $this->view->registerAssetBundle(KanbanAsset::class);

        $tasks = Task::find()
            ->cache(60)
            ->alias('t')
            ->with(['checklistElements', 'comments'])
            ->innerJoinWith('bucket bu')
            ->innerJoinWith('board b')
            ->innerJoinWith('assignments u')
            ->where(['not', ['{{t}}.[[status]]' => Task::STATUS_DONE]])
            ->andWhere(['{{u}}.[[user_id]]' => Yii::$app->user->id]);

        $results = $tasks->all();
        usort($results, function ($a, $b) {
            /** @var $a Task */
            /** @var $b Task */
            if ($a->endDate === $b->endDate) {
                return 0;
            }
            if ($a->endDate === null && $b->endDate !== null) {
                return 1;
            }
            if ($a->endDate !== null && $b->endDate === null) {
                return -1;
            }
            return ($a->endDate < $b->endDate) ? -1 : 1;
        });

        ob_start();
        if ($this->renderModal) {
            $js = <<<JS
function onHide() {
    jQuery('.note-editor', this).each(function () {
        var summernote = jQuery(this).prev().data('summernote');
        if (summernote) {
            summernote.destroy();
        }
    });
}
JS;
            echo Modal::widget([
                'options' => [
                    'id' => 'task-modal',
                    'options' => [
                        'class' => ['modal', 'remote', 'fade']
                    ],
                    'clientOptions' => [
                        'backdrop' => 'static',
                        'keyboard' => false
                    ],
                    'clientEvents' => ['hidden.bs.modal' => new JsExpression($js)],
                    'size' => \yii\bootstrap4\Modal::SIZE_EXTRA_LARGE,
                    'title' => null,
                    'closeButton' => false
                ],
            ]);
        }
        Frame::begin(['options' => ['id' => 'kanban-todo-frame']]);
        echo Html::beginTag('div', $this->options);
        if ($this->addBoardFilter) {
            $filters = Yii::$app->request->getBodyParam('ToDo', []);
            $boards = ArrayHelper::map(ArrayHelper::getColumn($results, 'board'), 'id', 'name');

            if (isset($filters['boardId'])) {
                $results = array_filter($results, function ($item) use ($filters) {
                    return ArrayHelper::getValue($item, 'board.id') == $filters['boardId'];
                });
            }
            echo Html::beginTag('div', [
                'class' => ['sa-todo-filter', 'mb-3']
            ]);
            echo Html::beginForm(["/{$this->kanbanModuleName}/plan/index", 'activeTab' => 'todo'], 'POST', [
                'id' => $this->options['id'] . '-filter-form'
            ]);
            echo Select2::widget([
                'name' => 'ToDo[boardId]',
                'value' => ArrayHelper::getValue($filters, 'boardId'),
                'theme' => Select2::THEME_BOOTSTRAP,
                'data' => $boards,
                'options' => [
                    'placeholder' => Yii::t('simialbi/kanban', 'Filter by board'),
                ],
                'pluginOptions' => [
                    'allowClear' => true
                ],
                'pluginEvents' => [
                    'change' => new JsExpression('function () { jQuery(this).closest(\'form\').submit(); }')
                ]
            ]);
            echo Html::endForm();
            echo Html::endTag('div');
        }
        echo Html::beginTag('div', $this->listOptions);

        foreach ($results as $task) {
            $options = $this->itemOptions;
            $options['href'] = Url::to([
                "/{$this->kanbanModuleName}/task/update",
                'id' => $task['id'],
                'return' => 'todo'
            ]);
            $options['data'] = [
                'toggle' => 'modal',
                'pjax' => '0',
                'turbo-frame' => 'task-modal-frame',
                'target' => '#task-modal'
            ];

            $subject = $task['subject'];
            if ($task->isRecurrentInstance()) {
                $subject = FAS::i('infinity', [
                    'data' => [
                        'fa-transform' => 'shrink-4.5',
                        'fa-mask' => 'fas fa-circle'
                    ]
                ]) . $subject;
            }
            $content = Html::tag('h6', $subject, ['class' => ['m-0']]);
            $small = $task['board']['name'];

            if (($cnt = count($task['checklistElements'])) > 0) {
                $grouped = ArrayHelper::index($task['checklistElements'], null, 'is_done');
                $done = count(ArrayHelper::getValue($grouped, '1', []));
                $all = $cnt;

                $small .= "&nbsp;&bull;&nbsp; $done/$all";
            }
            if ($task['endDate']) {
                if ($task['endDate'] < time()) {
                    Html::addCssClass($options, 'list-group-item-danger');
                } elseif ($task['start_date'] && $task['start_date'] <= time()) {
                    Html::addCssClass($options, 'list-group-item-info');
                }
                $small .= '&nbsp;&bull;&nbsp;' . FAR::i('calendar') . ' ';
                $small .= Yii::$app->formatter->asDate($task['endDate'], 'short');
            }
            if (!empty($task['comments'])) {
                $small .= '&nbsp;&bull;&nbsp;' . FAR::i('sticky-note');
            }
            $content .= Html::tag('small', $small);

            echo Html::beginTag('a', $options);
            echo Html::beginTag('div', [
                'class' => ['form-check']
            ]);
            echo Html::checkbox("check[{$task['id']}]", false, [
                'value' => Task::STATUS_DONE,
                'class' => ['form-check-input'],
                'id' => 'sa-kanban-status-' . $task['id'],
                'data' => [
                    'task-id' => $task['id']
                ]
            ]);

            echo Html::label($content, 'sa-kanban-status-' . $task['id'], [
                'class' => ['form-check-label']
            ]);
            echo Html::endTag('div');
            echo Html::endTag('a');
        }

        echo Html::endTag('div');
        echo Html::endTag('div');

        Frame::end();

        $this->registerPlugin();

        return ob_get_clean();
    }

    /**
     * {@inheritDoc}
     */
    protected function registerPlugin($pluginName = null, $selector = null)
    {
        $id = $this->options['id'];
        $url = Url::to(["/{$this->kanbanModuleName}/task/set-status"]);
        $js = <<<JS
jQuery('#$id label').on('click.sa.kanban', function (e) {
    // debugger;
    e.preventDefault();
    // window.location.replace(jQuery(this).closest('a').prop('href'));
});
jQuery('#$id input[type="checkbox"]').on('click', function (e) {
    e.stopImmediatePropagation();
    e.stopPropagation();
    e.preventDefault();
	var that = jQuery(this);
	var id = that.data('taskId');
	that.prop('checked', true);
	$.get('$url?id=' + id + '&status=0', function () {
		that.closest('.list-group-item').remove();
	});
});
JS;

        $this->view->registerJs($js);
    }
}
