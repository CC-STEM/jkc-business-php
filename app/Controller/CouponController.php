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
namespace App\Controller;

use App\Service\CouponService;

class CouponController extends AbstractController
{

    /**
     * 优惠券模板列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function couponTemplateList()
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $couponService = new CouponService();
            $couponService->offset = $offset;
            $couponService->limit = $pageSize;
            $result = $couponService->couponTemplateList();
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count' => $result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'couponTemplateList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 优惠券发放
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function issuedCoupon()
    {
        try {
            $params = $this->request->post();
            $couponService = new CouponService();
            $result = $couponService->issuedCoupon($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'issuedCoupon');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 优惠券发放列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function issuedCouponList()
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $id = $this->request->query('id');

            $params = ['id'=>$id];
            $couponService = new CouponService();
            $couponService->offset = $offset;
            $couponService->limit = $pageSize;
            $result = $couponService->issuedCouponList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count' => $result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'issuedCouponList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
