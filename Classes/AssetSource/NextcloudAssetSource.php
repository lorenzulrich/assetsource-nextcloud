<?php
declare(strict_types=1);

namespace DL\AssetSource\Nextcloud\AssetSource;

/*
 * This file is part of the DL.AssetSource.Nextcloud package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use DL\AssetSource\Nextcloud\NextcloudApi\WebDav\Dto\NextcloudAsset;
use Neos\Flow\Annotations as Flow;
use DL\AssetSource\Nextcloud\Exception\NextcloudAssetSourceException;
use DL\AssetSource\Nextcloud\NextcloudApi\NextcloudClient;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetSource\AssetProxyRepositoryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Media\Domain\Service\FileTypeIconService;

final class NextcloudAssetSource implements AssetSourceInterface
{

    /**
     * @var mixed[]
     */
    private $assetSourceOptions;

    /**
     * @var string
     */
    private $assetSourceIdentifier;

    /**
     * @var AssetProxyRepositoryInterface
     */
    private $assetProxyRepository;

    /**
     * @var string
     */
    private $label;

    /**
     * @var int
     */
    private $maxItemLimit;

    /**
     * @var NextcloudClient
     */
    private $NextcloudClient;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @Flow\Inject
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @param string $assetSourceIdentifier
     * @param array $assetSourceOptions
     * @throws NextcloudAssetSourceException
     */
    public function __construct(string $assetSourceIdentifier, array $assetSourceOptions)
    {
        $this->assetSourceIdentifier = $assetSourceIdentifier;
        $this->assetSourceOptions = $assetSourceOptions;

        $this->label = $this->assetSourceOptions['label'] ?? 'Nextcloud';
        $this->maxItemLimit = $this->assetSourceOptions['maxItemLimit'] ?? 300;

        if (empty($this->assetSourceOptions['server']) || empty($this->assetSourceOptions['server']['baseUri']) || empty($this->assetSourceOptions['server']['userName']) || empty($this->assetSourceOptions['server']['password'])) {
            throw new NextcloudAssetSourceException('The given server configuration is not complete.');
        }

        $this->NextcloudClient = new NextcloudClient($this->assetSourceOptions);
    }

    public function initializeObject()
    {
        $this->uriBuilder->setRequest($this->createActionRequest());
    }

    /**
     * @param string $assetSourceIdentifier
     * @param array $assetSourceOptions
     * @return AssetSourceInterface
     * @throws NextcloudAssetSourceException
     */
    public static function createFromConfiguration(string $assetSourceIdentifier, array $assetSourceOptions): AssetSourceInterface
    {
        return new static($assetSourceIdentifier, $assetSourceOptions);
    }

    /**
     * A unique string which identifies the concrete asset source.
     * Must match /^[a-z][a-z0-9-]{0,62}[a-z]$/
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->assetSourceIdentifier;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return int
     */
    public function getMaxItemLimit(): int
    {
        return $this->maxItemLimit;
    }

    /**
     * @return NextcloudClient
     */
    public function getNextcloudClient(): NextcloudClient
    {
        return $this->NextcloudClient;
    }

    /**
     * @return AssetProxyRepositoryInterface
     */
    public function getAssetProxyRepository(): AssetProxyRepositoryInterface
    {
        if ($this->assetProxyRepository === null) {
            $this->assetProxyRepository = new NextcloudAssetProxyRepository($this);
        }

        return $this->assetProxyRepository;
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * @param NextcloudAsset $NextcloudAsset
     * @param int $width
     * @param int $height
     * @return Uri
     * @throws MissingActionNameException
     */
    public function getThumbnailUrl(NextcloudAsset $NextcloudAsset, int $width, int $height): Uri
    {
        if (!$this->NextcloudSupportsThumbnailGeneration($NextcloudAsset)) {
            $icon = FileTypeIconService::getIcon($NextcloudAsset->getFileName());
            return new Uri($this->resourceManager->getPublicPackageResourceUriByPath($icon['src']));
        }

        $arguments = [
            'assetSourceIdentifier' => $this->getIdentifier(),
            'fileId' => $NextcloudAsset->getFileId(),
            'width' => $width,
            'height' => $height
        ];

        return new Uri($this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->uriFor('thumbnail', $arguments, 'Thumbnail', 'DL.AssetSource.Nextcloud')
        );
    }

    /**
     * @param NextcloudAsset $NextcloudAsset
     * @return bool
     */
    private function NextcloudSupportsThumbnailGeneration(NextcloudAsset $NextcloudAsset): bool
    {
        $supportedTypes = $this->assetSourceOptions['enabledPreviewProviders'] ?? [];
        foreach ($supportedTypes as $NextcloudProvider => $mimeTypeRegex) {
            if (preg_match($mimeTypeRegex, $NextcloudAsset->getContentType())) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return ActionRequest|null
     */
    private function createActionRequest(): ?ActionRequest
    {
        $requestHandler = $this->bootstrap->getActiveRequestHandler();
        if ($requestHandler instanceof HttpRequestHandlerInterface) {
            return new ActionRequest($requestHandler->getHttpRequest());
        }
        return null;
    }
}
