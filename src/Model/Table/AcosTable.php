<?php

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Acl\Model\Table;

use Acl\Model\Table\AclNodesTable;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Utility\Hash;

/**
 * Access Control Object
 *
 */
class AcosTable extends AclNodesTable
{

    /**
     * {@inheritDoc}
     *
     * @param array $config Config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setAlias('Acos');
        $this->setTable('acos');
        $this->addBehavior('Tree', ['type' => 'nested']);

        $this->belongsToMany('Aros', [
            'through' => App::className('Acl.PermissionsTable', 'Model/Table'),
            'className' => App::className('Acl.ArosTable', 'Model/Table'),
        ]);
        $this->hasMany('AcoChildren', [
            'className' => App::className('Acl.AcosTable', 'Model/Table'),
            'foreignKey' => 'parent_id'
        ]);
        $this->setEntityClass(App::className('Acl.Aco', 'Model/Entity'));
    }

    public function addAcoByPath($path)
    {
        $this->cacheQueries = false;

        if (is_string($path)) {
            $path = explode('/', $path);
        }
        if (!is_array($path)) {
            throw new NotImplementedException(__("Path can only be string or array"));
        }

        $parent = null;
        // $gotTransaction = $this->getDataSource()->begin();
        foreach ($path as $el) {

            $conditions = [
                'Acos.alias' => $el
            ];
            if (!empty($parent)) {
                $conditions['Acos.parent_id'] = $parent;
            } else {
                $conditions['Acos.parent_id IS'] = $parent;
            }

            $x = $this->find('all', [
                'contain' => [],
                'conditions' => $conditions
            ])->first();
            if ($x) {
                $parent = $x->id;
                continue;
            }
            $newAco = $this->newEmptyEntity();
            $newAco = $this->patchEntity($newAco, ['Acos' => ['alias' => $el, 'parent_id' => $parent]]);
            $saved = $this->save($newAco);
            if (!$saved) {
                // CakeLog::error("Error in updating Aco model table", 'acl');
                // if ($gotTransaction) {
                //     $this->getDataSource()->rollback();
                // }
                return;
            }

            $parent = $saved->id;
        }
        // if ($gotTransaction) {
        //     $this->getDataSource()->commit();
        // }
        // CakeLog::info(__("Created missing acl: %s", implode("/", $path)), 'acl');
        // CakeLog::info(Debugger::trace(), 'acl');
    }

    public function node($ref = null)
    {
        if (is_array($ref)) {
            if ($ref[0] != "controllers") {
                array_unshift($ref, "controllers");
            }
        } else if (is_string($ref)) {
            if ($ref[0] === "/") {
                $ref = "controllers" . $ref;
            }
            if (strpos($ref, "controllers/") !== 0) {
                $ref = "controllers/" . $ref;
            }
        }

        //        CakeLog::debug("ACL:".print_r($ref,true)."\n".Debugger::trace(),'acl');
        //        var_dump($ref);
        $ret = parent::node($ref);
        //        var_dump($ret);
        //        $this->recover();exit;

        if ($ret) {
            return $ret;
        }
        if (!Configure::read("Acl.addMissingAco")) {
            return $ret;
        }

        $this->addAcoByPath($ref);
        //        var_dump($ref);
        //        var_dump($ret);

        return $ret;
    }
}
