<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\ServiceProvider;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\ItemHolderInterface;
use Eccube\EventListener\ForwardOnlyListener;
use Eccube\EventListener\TransactionListener;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\DeliveryRepository;
use Eccube\Service\PurchaseFlow\Processor\AdminOrderRegisterPurchaseProcessor;
use Eccube\Service\PurchaseFlow\Processor\DeliveryFeeFreeProcessor;
use Eccube\Service\PurchaseFlow\Processor\DeliveryFeeProcessor;
use Eccube\Service\PurchaseFlow\Processor\DeliverySettingValidator;
use Eccube\Service\PurchaseFlow\Processor\DisplayStatusValidator;
use Eccube\Service\PurchaseFlow\Processor\PaymentProcessor;
use Eccube\Service\PurchaseFlow\Processor\PaymentTotalLimitValidator;
use Eccube\Service\PurchaseFlow\Processor\PaymentTotalNegativeValidator;
use Eccube\Service\PurchaseFlow\Processor\SaleLimitValidator;
use Eccube\Service\PurchaseFlow\Processor\StockValidator;
use Eccube\Service\PurchaseFlow\Processor\UpdateDatePurchaseProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\TaxRuleService;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class EccubeServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param BaseApplication $app An Application instance
     */
    public function register(Container $app)
    {
        $app[BaseInfo::class] = function () use ($app) {
            return $app[BaseInfoRepository::class]->get();
        };

        $app['request_scope'] = function () {
            return new ParameterBag();
        };

        $app['eccube.twig.block.templates'] = function () {
            $templates = new ArrayCollection();
            $templates[] = 'render_block.twig';

            return $templates;
        };

        $app['eccube.queries'] = function () {
            return new \Eccube\Doctrine\Query\Queries();
        };
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        // Add event subscriber to TaxRuleEvent
        $app['orm.em']->getEventManager()->addEventSubscriber(new \Eccube\Doctrine\EventSubscriber\TaxRuleEventSubscriber($app[TaxRuleService::class]));

        $dispatcher->addSubscriber(new ForwardOnlyListener());
        $dispatcher->addSubscriber(new TransactionListener($app));
    }
}
