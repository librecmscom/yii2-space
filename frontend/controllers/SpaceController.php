<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\space\frontend\controllers;

use Yii;
use yii\web\Response;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\data\ActiveDataProvider;
use yuncms\tag\models\Tag;
use yuncms\user\models\User;
use yuncms\doing\models\Doing;
use yuncms\space\jobs\VisitJob;
use yuncms\space\models\Visit;

/**
 * SpaceController shows users space.
 *
 * @property \yuncms\space\Module $module
 */
class SpaceController extends Controller
{
    /** @inheritdoc */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'tag'],
                        'roles' => ['@']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['view', 'show'],
                        'roles' => ['?', '@']
                    ]
                ]
            ]
        ];
    }

    /**
     * Redirects to current user's space.
     *
     * @return string
     */
    public function actionIndex()
    {
        $model = $this->findModel(Yii::$app->user->id);
        $dataProvider = $this->getDoingDataProvider($model->id);
        return $this->render('view', [
            'model' => $model,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * Shows user's space.
     * @param string $username
     * @return string
     */
    public function actionShow($username)
    {
        $model = $this->findModelByUsername($username);
        if (!Yii::$app->user->isGuest && Yii::$app->has('queue')) {
            Yii::$app->queue->push(new VisitJob([
                'user_id' => Yii::$app->user->id,
                'source_id' => $model->id
            ]));
        }
        $dataProvider = $this->getDoingDataProvider($model->id);
        return $this->render('view', [
            'model' => $model,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * Shows user's space.
     *
     * @param int $id
     * @return string
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        if (!Yii::$app->user->isGuest && Yii::$app->has('queue')) {
            Yii::$app->queue->push(new VisitJob([
                'user_id' => Yii::$app->user->id,
                'source_id' => $model->id
            ]));
        }

        $dataProvider = $this->getDoingDataProvider($model->id);

        return $this->render('view', [
            'model' => $model,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * 关注某tag
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionTag()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $sourceId = Yii::$app->request->post('sourceId', null);
        $source = Tag::findOne($sourceId);
        if (!$source) {
            throw new NotFoundHttpException ();
        }
        /** @var \yuncms\user\models\User $user */
        $user = Yii::$app->user->identity;
        if ($user->hasTagValues($source->id)) {
            $user->removeTagValues($source->id);
            $user->save();
            return ['status' => 'unfollowed'];
        } else {
            $user->addTagValues($source->id);
            $user->save();
            return ['status' => 'followed'];
        }
    }

    /**
     * 获取个人动态
     * @param int $user_id
     * @return ActiveDataProvider
     */
    protected function getDoingDataProvider($user_id)
    {
        $query = Doing::find()->where(['user_id' => $user_id])->orderBy(['created_at' => SORT_DESC]);
        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pagesize' => 15,
            ]
        ]);
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param integer $id
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t('yii', 'The requested page does not exist.'));
        }
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param string $username
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModelByUsername($username)
    {
        if (($model = User::findModelByUsername($username)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t('yii', 'The requested page does not exist.'));
        }
    }
}
