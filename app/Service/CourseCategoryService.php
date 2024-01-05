<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\AdminsCache;
use App\Model\CourseOfflineCategory;
use App\Constants\ErrorCode;
use App\Model\PhysicalStoreExt;
use Hyperf\Utils\Context;

class CourseCategoryService extends BaseService
{
    /**
     * CourseCategoryService constructor.
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 线下课程分类列表
     * @param array $params
     * @return array
     */
    public function courseOfflineCategoryList(array $params): array
    {
        $parentId = $params['parent_id'];
        $themeType = $params['theme_type'];

        $courseOfflineCategoryList = CourseOfflineCategory::query()
            ->select(['id','name','type'])
            ->where(['parent_id'=>$parentId,'theme_type'=>$themeType])
            ->get();
        $courseOfflineCategoryList = $courseOfflineCategoryList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineCategoryList];
    }

    /**
     * 线下课程主题类型列表
     * @return array
     */
    public function courseOfflineThemeTypeList(): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $themeTypeList = [['theme_name'=>'常规班','theme_value'=>1]];

        $physicalStoreExtInfo = PhysicalStoreExt::query()
            ->select(['course_offline_theme2_enabled','course_offline_theme3_enabled'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->first();
        if(empty($physicalStoreExtInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $themeTypeList];
        }
        $physicalStoreExtInfo = $physicalStoreExtInfo->toArray();
        if($physicalStoreExtInfo['course_offline_theme2_enabled'] == 1){
            $themeTypeList[] = ['theme_name'=>'精品小班','theme_value'=>2];
        }
        if($physicalStoreExtInfo['course_offline_theme3_enabled'] == 1){
            $themeTypeList[] = ['theme_name'=>'代码编程','theme_value'=>3];
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $themeTypeList];
    }
}