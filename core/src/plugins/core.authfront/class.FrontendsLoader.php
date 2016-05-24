<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
namespace Pydio\Authfront\Core;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Log\Core\AJXP_Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

class FrontendsLoader extends Plugin {

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return null|ResponseInterface
     */
    public static function frontendsAsAuthMiddlewares(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface){

        if(AuthService::usersEnabled()){

            PluginsService::getInstance()->initActivePlugins();
            $frontends = PluginsService::getInstance()->getActivePluginsForType("authfront");
            $index = 0;
            /**
             * @var AbstractAuthFrontend $frontendPlugin
             */
            foreach($frontends as $frontendPlugin){
                if(!$frontendPlugin->isEnabled()) continue;
                if(!method_exists($frontendPlugin, "tryToLogUser")){
                    AJXP_Logger::error(__CLASS__, __FUNCTION__, "Trying to use an authfront plugin without tryToLogUser method. Wrongly initialized?");
                    continue;
                }
                //$res = $frontendPlugin->tryToLogUser($httpVars, ($index == count($frontends)-1));
                $isLast = ($index == count($frontends)-1);
                $res = $frontendPlugin->tryToLogUser($requestInterface, $responseInterface, $isLast);
                $index ++;
                if($res) {
                    if($responseInterface->getBody()->getSize() > 0 || $responseInterface->getStatusCode() != 200){
                        // Do not go to the other middleware, return directly.
                        return $responseInterface;
                    }
                    break;
                }
            }

        }

        return null;

    }

    public function init($options){

        parent::init($options);

        // Load all enabled frontend plugins
        $fronts = PluginsService::getInstance()->getPluginsByType("authfront");
        usort($fronts, array($this, "frontendsSort"));
        foreach($fronts as $front){
            if($front->isEnabled()){
                $configs = $front->getConfigs();
                $protocol = $configs["PROTOCOL_TYPE"];
                if($protocol == "session_only" && !AuthService::$useSession) continue;
                if($protocol == "no_session" && AuthService::$useSession) continue;
                PluginsService::setPluginActive($front->getType(), $front->getName(), true);
            }
        }

    }

    /**
     * @param \Pydio\Core\PluginFramework\Plugin $a
     * @param \Pydio\Core\PluginFramework\Plugin $b
     * @return int
     */
    public function frontendsSort($a, $b){
        $aConf = $a->getConfigs();
        $bConf = $b->getConfigs();
        $orderA = intval($aConf["ORDER"]);
        $orderB = intval($bConf["ORDER"]);
        if($orderA == $orderB) return 0;
        return $orderA > $orderB ? 1 : -1;
    }

} 