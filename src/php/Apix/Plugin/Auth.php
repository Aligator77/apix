<?php
/**
 *
 * This file is part of the Apix Project.
 *
 * (c) Franck Cassedanne <franck at ouarz.net>
 *
 * @license     http://opensource.org/licenses/BSD-3-Clause  New BSD License
 *
 */

namespace Apix\Plugin;

use Apix\Service,
    Apix\Exception;

class Auth extends PluginAbstractEntity
{
    public static $hook = array('entity', 'early');

    protected $options = array(
        // 'enable'        => true,        // wether to enable or not
        'adapter'       => 'Apix\Plugin\Auth\Adapter',
        'public_group'  => 'public',    // public group to skip auth
    );

    protected $annotation = 'api_auth';

    public function update(\SplSubject $entity)
    {
        $this->entity = $entity;

        $groups = $this->getSubTagValues('groups');
        $users = $this->getSubTagValues('users');

        // skip auth if groups and users are both null
        // or if the group is public.
        if(
            null === $users
            && null === $groups
            || null !== $groups
            && in_array($this->options['public_group'], $groups)
        ) {
            return null;
        }

        // authenticate
        if ( !$this->adapter->authenticate() ) {
            // $this->log('Login failed', $this->adapter->getUsername(), 'INFO');
            $this->adapter->send();

            // TODO: eventually
            // $response = Service::get('response');
            // $response->setHeaders($headers);
            // $response->send();

            throw new Exception('Authentication required', 401);
        }

        // TODO: get the Session object.
        if (Service::has('session')) {
            $session = Service::get('session');
            $username = $session->getUsername();

            // check the username is in the authorised list.
            if (null !== $users && !in_array($username, $users)) {
                $this->log('User unauthorised', $username, 'INFO');
                throw new Exception('Access unauthorised', 401);
            }

            // check user group
            $group = $session->getGroup();
            if (null !== $groups && !in_array($group, $groups) ) {
                $this->log('Group unauthorised.', array($username, $group), 'INFO');
                throw new Exception('Access unauthorised.', 401);
            }

            // check for (required) trusted user IPs
            if ($session->hasTrustedIps()) {
                $ip = Service::get('response')->getRequest()->getIp();
                if (!$this->isTrustedIp($ip, $session->getTrustedIps())) {
                    $this->log('Session\'s IP not trusted.', array($username, $ip), 'INFO');
                    throw new Exception('Session\'s IP not trusted', 401);
                }
            }

            // TODO: set X_REMOTE_USER or X_AUTH_USER
            $_SERVER['X_AUTH_USER'] = $username;
            $this->log('Login', $username, 'NOTICE');
        }

        return true;
    }

    protected function isTrustedIp($ip, array $ips)
    {
        // TODO: improve this, check IP ranges, etc...
        return in_array($ip, $ips);
    }

}
