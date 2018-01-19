<?php
declare(strict_types=1);
namespace ParagonIE\MonologQuill;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Quill\Quill;
use ParagonIE\Sapient\CryptographyKeys\{
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
        $this->quill->blindWrite($data);
    }
}
