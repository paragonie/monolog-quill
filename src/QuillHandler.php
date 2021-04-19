<?php
declare(strict_types=1);
namespace ParagonIE\MonologQuill;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Quill\Quill;
use ParagonIE\Sapient\CryptographyKey;
use ParagonIE\Sapient\CryptographyKeys\{
    SealingPublicKey,
    SharedEncryptionKey,
    SigningPublicKey,
    SigningSecretKey
};

/**
 * Class QuillHandler
 * @package ParagonIE\MonologQuill
 * @psalm-suppress PropertyNotSetInConstructor
 */
class QuillHandler extends AbstractProcessingHandler
{
    /** @var SharedEncryptionKey|null $encryptionKey */
    protected $encryptionKey = null;

    /** @var SealingPublicKey|null $sealingKey */
    protected $sealingKey = null;

    /** @var Quill $quill */
    protected $quill;

    /**
     * @param string $url
     * @param string $clientId
     * @param SigningPublicKey|null $serverPublicKey
     * @param SigningSecretKey|null $clientSecretKey
     * @param Client|null $http
     * @param int $level
     * @param bool $bubble
     * @return self
     *
     * @psalm-suppress UnsafeInstantiation   We want this to be subclassable
     */
    public static function factory(
        string $url = '',
        string $clientId = '',
        SigningPublicKey $serverPublicKey = null,
        SigningSecretKey $clientSecretKey = null,
        Client $http = null,
        int $level = Logger::DEBUG,
        bool $bubble = true
    ): self {
        return new static(
            new Quill($url, $clientId, $serverPublicKey, $clientSecretKey, $http),
            $level,
            $bubble
        );
    }

    /**
     * QuillHandler constructor.
     *
     * @param Quill $quill
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(Quill $quill, int $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->quill = $quill;
        /** @psalm-suppress InvalidArgument -- Monolog says "Boolean" instead of "bool". */
        parent::__construct($level, $bubble);
    }

    /**
     * @param CryptographyKey $encKey
     * @return self
     * @throws \TypeError
     */
    public function setEncryptionKey(CryptographyKey $encKey = null): self
    {
        if (\is_null($encKey)) {
            $this->sealingKey = null;
            $this->encryptionKey = null;
        } elseif ($encKey instanceof SealingPublicKey) {
            $this->sealingKey = $encKey;
            $this->encryptionKey = null;
        } elseif ($encKey instanceof SharedEncryptionKey) {
            $this->sealingKey = null;
            $this->encryptionKey = $encKey;
        } else {
            throw new \TypeError('Invalid key type.');
        }
        return $this;
    }

    /**
     * @param array $record
     * @return void
     * @throws \Error
     */
    protected function write(array $record)
    {
        $data = \json_encode($record);
        if (!\is_string($data)) {
            try {
                $data = \json_encode([
                    'serialized-due-to-utf8-errors' =>
                        Base64UrlSafe::encode(\serialize($record))
                ]);
                if (!\is_string($data)) {
                    throw new \TypeError('Could not serialize record.');
                }
            } catch (\Throwable $ex) {
                // Last resort
                $data = $ex->getMessage() . PHP_EOL . $ex->getTraceAsString();
            }
        }
        if (!\is_null($this->encryptionKey)) {
            $this->quill->blindWriteEncrypted($data, $this->encryptionKey);
        } elseif (!\is_null($this->sealingKey)) {
            $this->quill->blindWriteSealed($data, $this->sealingKey);
        } else {
            $this->quill->blindWrite($data);
        }
    }
}
