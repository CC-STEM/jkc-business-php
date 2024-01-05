<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;
use App\Middleware\AuthMiddleware;

//登录
Router::addGroup('/login/',function (){
    Router::post('code',[\App\Controller\AuthController::class, 'smsCodeSend']);
    Router::post('mobile',[\App\Controller\AuthController::class, 'mobileLogin']);
    Router::post('selected_identity',[\App\Controller\AuthController::class, 'selectedAdminsIdentity']);
    Router::post('out',[\App\Controller\AuthController::class, 'loginOut'],['middleware' => [AuthMiddleware::class]]);

});

//首页
Router::get('/', [\App\Controller\IndexController::class, 'index'], ['middleware' => [AuthMiddleware::class]]);
Router::get('/class_statistics',[\App\Controller\IndexController::class, 'classStatistics'], ['middleware' => [AuthMiddleware::class]]);
Router::post('/roll_call',[\App\Controller\IndexController::class, 'teacherRollCall'], ['middleware' => [AuthMiddleware::class]]);

//门店
Router::addGroup('/physical_store/',function (){
    Router::post('add_classroom',[\App\Controller\PhysicalStoreController::class, 'addPhysicalStoreClassroom']);
    Router::post('edit_classroom',[\App\Controller\PhysicalStoreController::class, 'editPhysicalStoreClassroom']);
    Router::get('classroom_list',[\App\Controller\PhysicalStoreController::class, 'physicalStoreClassroomList']);
    Router::get('classroom_detail',[\App\Controller\PhysicalStoreController::class, 'physicalStoreClassroomDetail']);
    Router::post('delete_classroom',[\App\Controller\PhysicalStoreController::class, 'deletePhysicalStoreClassroom']);
    Router::post('add_teacher',[\App\Controller\PhysicalStoreController::class, 'addPhysicalStoreTeacher']);
    Router::post('edit_teacher',[\App\Controller\PhysicalStoreController::class, 'editPhysicalStoreTeacher']);
    Router::get('teacher_list',[\App\Controller\PhysicalStoreController::class, 'physicalStoreTeacherList']);
    Router::get('teacher_detail',[\App\Controller\PhysicalStoreController::class, 'physicalStoreTeacherDetail']);
    Router::post('delete_teacher',[\App\Controller\PhysicalStoreController::class, 'deletePhysicalStoreTeacher']);

}, ['middleware' => [AuthMiddleware::class]]);

//课程分类
Router::addGroup('/course_category/',function (){
    Router::get('list_offline',[\App\Controller\CourseCategoryController::class, 'courseOfflineCategoryList']);
    Router::get('list_offline_theme',[\App\Controller\CourseCategoryController::class, 'courseOfflineThemeTypeList']);

}, ['middleware' => [AuthMiddleware::class]]);

//线下课程
Router::addGroup('/offline_course/',function (){
    Router::get('list',[\App\Controller\CourseController::class, 'courseOfflineList']);
    Router::post('plan_add',[\App\Controller\CourseController::class, 'addCourseOfflinePlan']);
    Router::post('plan_edit',[\App\Controller\CourseController::class, 'editCourseOfflinePlan']);
    Router::get('plan_list',[\App\Controller\CourseController::class, 'courseOfflinePlanList']);
    Router::get('plan_detail',[\App\Controller\CourseController::class, 'courseOfflinePlanDetail']);
    Router::post('plan_del',[\App\Controller\CourseController::class, 'deleteCourseOfflinePlan']);
    Router::get('plan_sign_up_student',[\App\Controller\CourseController::class, 'courseOfflinePlanSignUpStudent']);
    //Router::get('plan_arrive_student',[\App\Controller\CourseController::class, 'courseOfflinePlanArriveStudent']);
    Router::get('plan_classroom_situation',[\App\Controller\CourseController::class, 'courseOfflinePlanClassroomSituation']);
    Router::get('plan_info',[\App\Controller\CourseController::class, 'courseOfflinePlanInfo']);

}, ['middleware' => [AuthMiddleware::class]]);

//课程订单
Router::addGroup('/course_order/',function (){
    Router::get('reservation_member',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderCreateMemberInfo']);
    Router::get('reservation_screen_list',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderCreateScreenList']);
    Router::post('submit',[\App\Controller\CourseOrderController::class, 'courseOfflineCreateOrder']);
    Router::get('list',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderList']);
    Router::post('readjust',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderReadjust']);
    Router::get('readjust_screen_list',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderReadjustScreenList']);
    Router::get('offline_order_readjust_list',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderReadjustList']);
    Router::get('offline_order_readjust_detail',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderReadjustDetail']);
    Router::post('offline_order_readjust_handle',[\App\Controller\CourseOrderController::class, 'handleCourseOfflineOrderReadjust']);
    Router::post('cancel',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderCancel']);
    Router::get('offline_order_export',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderExport']);
    Router::get('evaluation_list',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderEvaluationList']);

}, ['middleware' => [AuthMiddleware::class]]);

//老师
Router::addGroup('/teacher/',function (){
    Router::post('add',[\App\Controller\TeacherController::class, 'addTeacher']);
    Router::post('edit',[\App\Controller\TeacherController::class, 'editTeacher']);
    Router::post('del',[\App\Controller\TeacherController::class, 'deleteTeacher']);
    Router::get('list',[\App\Controller\TeacherController::class, 'teacherList']);
    Router::get('search_list',[\App\Controller\TeacherController::class, 'teacherSearchList']);
    Router::get('detail',[\App\Controller\TeacherController::class, 'teacherDetail']);

}, ['middleware' => [AuthMiddleware::class]]);

//教学管理
Router::addGroup('/teaching/',function (){
    Router::get('plan_list',[\App\Controller\TeachingController::class, 'teachingPlanList']);

}, ['middleware' => [AuthMiddleware::class]]);

//教室
Router::addGroup('/classroom/',function (){
    Router::post('add',[\App\Controller\ClassroomController::class, 'addClassroom']);
    Router::post('edit',[\App\Controller\ClassroomController::class, 'editClassroom']);
    Router::post('del',[\App\Controller\ClassroomController::class, 'deleteClassroom']);
    Router::get('list',[\App\Controller\ClassroomController::class, 'classroomList']);
    Router::get('detail',[\App\Controller\ClassroomController::class, 'classroomDetail']);

}, ['middleware' => [AuthMiddleware::class]]);

//会员卡
Router::addGroup('/vip_card/',function (){
    Router::get('order_list',[\App\Controller\VipCardController::class, 'vipCardOrderList']);
    Router::get('newcomer_list',[\App\Controller\VipCardController::class, 'newcomerVipCardList']);
    Router::get('gift_order_list',[\App\Controller\VipCardController::class, 'giftVipCardOrderList']);
    Router::get('gift_order_detail',[\App\Controller\VipCardController::class, 'giftVipCardOrderDetail']);

}, ['middleware' => [AuthMiddleware::class]]);

//门店
Router::addGroup('/physical_store/',function (){
    Router::post('edit',[\App\Controller\PhysicalStoreController::class, 'editPhysicalStore']);
    Router::get('detail',[\App\Controller\PhysicalStoreController::class, 'physicalStoreDetail']);
    Router::post('selected_physical_store',[\App\Controller\PhysicalStoreController::class, 'selectedPhysicalStore']);
    Router::get('admins_physical_store',[\App\Controller\PhysicalStoreController::class, 'adminsPhysicalStoreList']);

}, ['middleware' => [AuthMiddleware::class]]);

//会员
Router::addGroup('/member/',function (){
    Router::get('list',[\App\Controller\MemberController::class, 'memberList']);
    Router::get('detail',[\App\Controller\MemberController::class, 'memberDetail']);
    Router::get('course_online',[\App\Controller\MemberController::class, 'courseOnlineCollectList']);
    Router::get('course_online_child',[\App\Controller\MemberController::class, 'courseOnlineChildCollectList']);
    Router::get('course_offline',[\App\Controller\MemberController::class, 'courseOfflineOrderList']);
    Router::get('teaching_aids',[\App\Controller\MemberController::class, 'teachingAidsOrderList']);
    Router::get('vip_card',[\App\Controller\MemberController::class, 'vipCardOrderList']);
    Router::post('vip_card_send',[\App\Controller\MemberController::class, 'vipCardOrderCreate']);
    Router::get('invitation_relation',[\App\Controller\MemberController::class, 'invitationRelationTree']);
    Router::get('search_list',[\App\Controller\MemberController::class, 'memberSearchList']);

}, ['middleware' => [AuthMiddleware::class]]);

//优惠券
Router::addGroup('/coupon/',function (){
    Router::get('list',[\App\Controller\CouponController::class, 'couponTemplateList']);
    Router::get('issued_list',[\App\Controller\CouponController::class, 'issuedCouponList']);
    Router::post('issued',[\App\Controller\CouponController::class, 'issuedCoupon']);

}, ['middleware' => [AuthMiddleware::class]]);

//薪资管理列表
Router::addGroup('/salary_bill/',function (){
    Router::get('list',[\App\Controller\SalaryBillController::class, 'salaryBillSearchList']);
    Router::post('adjust',[\App\Controller\SalaryBillController::class, 'salaryBillAdjust']);
    Router::get('export',[\App\Controller\SalaryBillController::class, 'salaryBillExport']);
    Router::get('bill_detailed_list',[\App\Controller\SalaryBillController::class, 'salaryBillDetailedList']);

}, ['middleware' => [AuthMiddleware::class]]);

//组织(管理员)
Router::addGroup('/organization/',function (){
    Router::post('add',[\App\Controller\OrganizationController::class, 'addAdmins']);
    Router::post('edit',[\App\Controller\OrganizationController::class, 'editAdmins']);
    Router::post('del',[\App\Controller\OrganizationController::class, 'deleteAdmins']);
    Router::get('list',[\App\Controller\OrganizationController::class, 'adminsList']);
    Router::get('detail',[\App\Controller\OrganizationController::class, 'adminsDetail']);

}, ['middleware' => [AuthMiddleware::class]]);

//权限
Router::addGroup('/permissions/',function (){
    Router::post('add',[\App\Controller\AdminPermissionsController::class, 'addAdminPermissions']);
    Router::post('edit',[\App\Controller\AdminPermissionsController::class, 'editAdminPermissions']);
    Router::post('del',[\App\Controller\AdminPermissionsController::class, 'deleteAdminPermissions']);
    Router::get('list',[\App\Controller\AdminPermissionsController::class, 'adminPermissionsList']);
    Router::get('detail',[\App\Controller\AdminPermissionsController::class, 'adminPermissionsDetail']);
    Router::get('route_list',[\App\Controller\AdminPermissionsController::class, 'adminRouteList']);

}, ['middleware' => [AuthMiddleware::class]]);

//管理员
Router::addGroup('/admins/',function (){
    Router::get('info',[\App\Controller\AdminsController::class, 'adminsInfo']);

}, ['middleware' => [AuthMiddleware::class]]);

//文件上传
Router::addGroup('/upload/',function (){
    Router::post('cos',[\App\Controller\UploadController::class, 'cosUpload']);

}, ['middleware' => [AuthMiddleware::class]]);

//待处理事项
Router::addGroup('/member_event/',function (){
    Router::get('trigger_action_set_list',[\App\Controller\MemberEventController::class, 'triggerActionSetList']);
    Router::get('auto_handle_judgment_criteria_set_list',[\App\Controller\MemberEventController::class, 'autoHandleJudgmentCriteriaSetList']);
    Router::post('add',[\App\Controller\MemberEventController::class, 'addMemberEvent']);
    Router::post('edit',[\App\Controller\MemberEventController::class, 'editMemberEvent']);
    Router::post('delete',[\App\Controller\MemberEventController::class, 'deleteMemberEvent']);
    Router::get('detail',[\App\Controller\MemberEventController::class, 'memberEventDetail']);
    Router::get('list',[\App\Controller\MemberEventController::class, 'memberEventList']);
    Router::get('all_list',[\App\Controller\MemberEventController::class, 'allMemberEventList']);
    Router::get('customer_table_list',[\App\Controller\MemberEventController::class, 'customerTableList']);
    Router::get('customer_list',[\App\Controller\MemberEventController::class, 'customerList']);
    Router::post('allocation_belong_to',[\App\Controller\MemberEventController::class, 'allocationMemberBelongTo']);
    Router::post('complete_event_followup',[\App\Controller\MemberEventController::class, 'completeEventFollowup']);
    Router::post('trigger_switch',[\App\Controller\MemberEventController::class, 'memberEventSwitch']);
    Router::get('complete_event_detail',[\App\Controller\MemberEventController::class, 'memberEventCompleteDetail']);
    Router::get('followup_list',[\App\Controller\MemberEventController::class, 'followupList']);
    Router::get('complete_list',[\App\Controller\MemberEventController::class, 'memberEventCompleteList']);
    Router::get('member_vip_card_order_list',[\App\Controller\MemberEventController::class, 'memberVipCardOrderList']);
    Router::get('member_course_offline_order_list',[\App\Controller\MemberEventController::class, 'memberCourseOfflineOrderList']);
    Router::post('member_followup_note',[\App\Controller\MemberEventController::class, 'memberFollowupNote']);

}, ['middleware' => [AuthMiddleware::class]]);

Router::get('/favicon.ico', function () {
    return '';
});
