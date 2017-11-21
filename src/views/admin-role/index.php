<?php
/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var skeeks\cms\models\searchs\AuthItemSearch $searchModel
 */
?>
<div class="role-index">

    <? $pjax = \skeeks\cms\modules\admin\widgets\Pjax::begin(); ?>

    <?php echo $this->render('_search', [
        'searchModel' => $searchModel,
        'dataProvider' => $dataProvider
    ]); ?>

    <?php

    echo \skeeks\cms\modules\admin\widgets\GridViewStandart::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'adminController' => $controller,
        'pjax' => $pjax,
        'settingsData' =>
            [
                'orderBy' => ''
            ],
        'columns' => [

            [
                'attribute' => 'name',
                'label' => \Yii::t('app', 'Name'),
            ],
            [
                'attribute' => 'description',
                'label' => \Yii::t('app', 'Description'),
            ],

            /*['class' => 'yii\grid\ActionColumn',],*/
        ],
    ]);

    ?>
    <? $pjax::end(); ?>
</div>