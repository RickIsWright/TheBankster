<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Rule;

class RuleController extends BaseController
{

    public function __invoke(Request $request, Response $response, $args)
    {
        $error = '';

        if (isset($args['id'])) {
            $rule = $this->container->db->fetch(Rule::class, $args['id']);
            if ($rule === null) throw new NotFoundException($request, $response);
        } else {
            $rule = new Rule();
        }

        if ($request->isPost()) {
            try {
                $this->applyPostData($rule, $request->getParsedBody());
                $rule = $rule->save();
                return $response->withRedirect($this->container->router->pathFor('rule', ['id' => $rule->id]));
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        if($rule->id) {
            $transactions = $rule->matchTransactionsQuery()->orderBy('ts','DESC')->all();
        } else {
            $transactions = [];
        }

        return $this->view->render($response, 'rule.twig', [
            'title' => ($rule->id) ? 'Edit Rule ' . $rule->id : 'Add a Rule',
            'accounts' => $this->getAccounts(),
            'categories' => $this->getCategories(),
            'rule' => $rule,
            'error' => $error,
            'transactions' => $transactions,
        ]);
    }

    protected function applyPostData(Rule $rule, array $post)
    {
        $ok = false;

        foreach (['account', 'debit', 'description', 'xName', 'xBank', 'xAcct'] as $key) {
            if (isset($post[$key])) {
                $rule->$key = $post[$key];
                if ($post[$key] !== '') $ok = true;
            }
        }
        if (isset($post['categoryId'])) {
            $rule->categoryId = $post['categoryId'];
        }
        if (!$ok) throw new \Exception('You need to provide at least one matching rule');
    }

    /**
     * Get list of available accounts
     *
     * @return array
     */
    protected function getAccounts()
    {
        $accounts = [];
        $accounts[''] = '';

        foreach ($this->container->settings['accounts'] as $key => $info) {
            $accounts[$key] = $info['label'];
        }
        return $accounts;
    }

    protected function getCategories()
    {
        $data = [];
        $cats = $this->container->db->fetch(Category::class)->orderBy('top')->orderBy('label')->all();
        foreach ($cats as $cat) {
            if (!isset($data[$cat->top])) $data[$cat->top] = [];
            $data[$cat->top][$cat->id] = $cat->label;
        }
        return $data;
    }
}