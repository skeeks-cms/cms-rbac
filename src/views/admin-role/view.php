<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View                $this
 * @var \skeeks\cms\models\AuthItem $model
 */
?>
    <div class="auth-item-view row">

        <?php
        echo DetailView::widget([
            'model'      => $model,
            'attributes' => [
                'name',
                'description:ntext',
                'ruleName',
                'data:ntext',
            ],
        ]);
        ?>
        <div class="col-lg-5">
            <div style="text-align: center;">
                <h4><?= Yii::t('skeeks/rbac', 'Avaliable'); ?></h4>
            </div>
            <div style="margin-bottom: 5px;">
                <?php
                echo Html::textInput('search_av', '', [
                        'class' => 'role-search form-control', 
                        'data-target' => 'avaliable', 
                        'style' => 'width: 100%; padding-bottom: 5px;', 
                        'placeholder' =>  'Поиск...'
                    ]); ?>
            </div>
            <?php
            echo Html::listBox('roles', '', $avaliable, [
                'id'       => 'avaliable',
                'multiple' => true,
                'size'     => 20,
                'style'    => 'width:100%',
            ]);
            ?>
        </div>
        <div class="col-lg-1 text-center">
            &nbsp;<br><br>
            <div style="margin-bottom: 5px;">
            <?php
            echo Html::a('>>', '#', ['class' => 'btn btn-success', 'data-action' => 'assign']).'<br>'; ?>
            </div>
            
            <?php
            echo Html::a('<<', '#', ['class' => 'btn btn-success', 'data-action' => 'delete']).'<br>';
            ?>
        </div>
        <div class="col-lg-5">
            <div style="text-align: center;">
                <h4><?= Yii::t('skeeks/rbac', 'Assigned') ?></h4>
            </div>
            
            <div style="margin-bottom: 5px;">
                <?php
                echo Html::textInput('search_asgn', '', [
                        'class' => 'role-search form-control', 
                        'data-target' => 'assigned', 
                        'style' => 'width: 100%', 
                        'placeholder' => 'Поиск...'
                    ]); ?>
            </div>

            <?php
            echo Html::listBox('roles', '', $assigned, [
                'id'       => 'assigned',
                'multiple' => true,
                'size'     => 20,
                'style'    => 'width:100%',
            ]);
            ?>
        </div>
    </div>
<?php
$this->render('_script', ['name' => $model->name]);