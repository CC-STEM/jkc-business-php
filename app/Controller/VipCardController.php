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

use App\Service\VipCardService;

class VipCardController extends AbstractController
{
    /**
     * 会员卡订单列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function vipCardOrderList()
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $mobile = $this->request->query('mobile');
            $cardName = $this->request->query('card_name');
            $startDate = $this->request->query('start_date');
            $endDate = $this->request->query('end_date');
            $memberName = $this->request->query('member_name');

            $params = [
                'mobile'=>$mobile,
                'card_name'=>$cardName,
                'start_date'=>$startDate,
                'end_date'=>$endDate,
                'member_name'=>$memberName,
            ];
            $vipCardService = new VipCardService();
            $vipCardService->offset = $offset;
            $vipCardService->limit = $pageSize;
            $result = $vipCardService->vipCardOrderList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count' => $result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'vipCardOrderList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 赠送会员卡订单列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function giftVipCardOrderList()
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $query = $this->request->query();

            $vipCardService = new VipCardService();
            $vipCardService->offset = $offset;
            $vipCardService->limit = $pageSize;
            $result = $vipCardService->giftVipCardOrderList($query);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count' => $result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'giftVipCardOrderList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }


    /**
     * 平台赠送会员卡详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function giftVipCardOrderDetail()
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $query = $this->request->query();

            $vipCardService = new VipCardService();
            $vipCardService->offset = $offset;
            $vipCardService->limit = $pageSize;
            $result = $vipCardService->giftVipCardOrderDetail($query);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize, 'count' => $result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e, 'giftVipCardOrderDetail');
        }

        return $this->responseSuccess($data, $result['msg'], $result['code']);
    }

    /**
     * 新人礼包会员卡列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function newcomerVipCardList()
    {
        try {
            $vipCardService = new VipCardService();
            $result = $vipCardService->newcomerVipCardList();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'newcomerVipCardList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
