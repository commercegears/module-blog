<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\ResourceModel\Author as AuthorResource;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;
use CommerceGears\Blog\Model\ResourceModel\Tag as TagResource;
use CommerceGears\Blog\Model\Router\PathResolver;

/**
 * Maps /<blog path>/... to the blog controllers and sends 301 redirects to the canonical address
 * (for example after the URL suffix was changed).
 */
class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ResponseInterface $response,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly PathResolver $pathResolver,
        private readonly PostResource $postResource,
        private readonly CategoryResource $categoryResource,
        private readonly TagResource $tagResource,
        private readonly AuthorResource $authorResource
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof HttpRequest || $request->getModuleName() === 'commercegears_blog') {
            return null;
        }
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }
        $requestPath = ltrim((string)$request->getPathInfo(), '/');
        $match = $this->pathResolver->resolve(
            $requestPath,
            $this->config->getRoute($storeId),
            $this->config->getUrlSuffix($storeId),
            [
                'category' => $this->config->getPrefix('category', $storeId),
                'tag' => $this->config->getPrefix('tag', $storeId),
                'author' => $this->config->getPrefix('author', $storeId),
            ]
        );
        if ($match === null) {
            return null;
        }
        $id = 0;
        switch ($match['type']) {
            case 'post':
                $id = $this->postResource->findIdByUrlKey($match['key'], $storeId);
                break;
            case 'category':
                $id = $this->categoryResource->findIdByUrlKey($match['key'], $storeId);
                break;
            case 'tag':
                $id = $this->tagResource->findIdByUrlKey($match['key'], $storeId);
                break;
            case 'author':
                $id = $this->authorResource->findIdByUrlKey($match['key']);
                break;
        }
        if (in_array($match['type'], ['post', 'category', 'tag', 'author'], true) && !$id) {
            return null;
        }
        if ($match['canonical'] !== null && $match['canonical'] !== $requestPath
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
        ) {
            $query = (string)$request->getServer('QUERY_STRING');
            $target = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK) . $match['canonical']
                . ($query !== '' ? '?' . $query : '');
            $this->response->setRedirect($target, 301);
            $request->setDispatched(true);
            return $this->actionFactory->create(Redirect::class);
        }

        $request->setModuleName('commercegears_blog')
            ->setControllerName($match['controller'])
            ->setActionName($match['action']);
        if ($id) {
            $request->setParam('id', $id);
        }
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $requestPath);
        return $this->actionFactory->create(Forward::class);
    }
}
