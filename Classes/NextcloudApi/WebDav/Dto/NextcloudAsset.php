<?php
declare(strict_types=1);

namespace DL\AssetSource\Nextcloud\NextcloudApi\WebDav\Dto;

/*
 * This file is part of the DL.AssetSource.Nextcloud package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

class NextcloudAsset
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var int
     */
    protected $fileId;

    /**
     * @var \DateTime
     */
    protected $lastModified;

    /**
     * @var string
     */
    protected $contentType;

    /**
     * @var int
     */
    protected $contentLength;

    /**
     * @var bool
     */
    protected $hasPreview;

    public function __construct(string $path, array $clarkResult)
    {
        $this->path = $path;
        $this->fileId = (int)($clarkResult['{http://owncloud.org/ns}fileid'] ?? 0);
        $this->lastModified = \DateTime::createFromFormat(DATE_RFC1123, $clarkResult['{DAV:}getlastmodified']);
        $this->contentType = $clarkResult['{DAV:}getcontenttype'] ?? '?/?';
        $this->contentLength = (int)($clarkResult['{DAV:}getcontentlength'] ?? 0);
        $this->hasPreview = isset($clarkResult['{http://Nextcloud.org/ns}has-preview']) && $clarkResult['{http://Nextcloud.org/ns}has-preview'] === 'true';
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return pathinfo(urldecode($this->path), PATHINFO_BASENAME);
    }

    /**
     * @return string
     */
    public function getFileExtension(): string
    {
        return pathinfo(urldecode($this->path), PATHINFO_EXTENSION);
    }

    /**
     * @return int
     */
    public function getFileId(): int
    {
        return $this->fileId;
    }

    /**
     * @return \DateTime
     */
    public function getLastModified(): \DateTime
    {
        return $this->lastModified;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return int
     */
    public function getContentLength(): int
    {
        return $this->contentLength;
    }

    /**
     * @return bool
     */
    public function isHasPreview(): bool
    {
        return $this->hasPreview;
    }
}
