<?php

declare(strict_types=1);

namespace Supseven\ThemeLibrary\Imaging;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Locking\LockFactory;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class GenerateMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const string ImageUrlPattern = '#/(?P<type>(ref|file))\\-(?P<id>\\d+)/(?P<processing>[a-z0-9]+)/(?P<key>[a-z0-9]+)\\.(?P<ext>[a-z]+)$#i';

    public function __construct(
        protected LockFactory $lockFactory,
        protected ImageGeneratorService $imageGeneratorService,
        protected StreamFactoryInterface $streamFactory,
        protected ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ((str_contains($path, '/ref-') || str_contains($path, '/file-')) && preg_match(self::ImageUrlPattern, $path, $match)) {
            $locker = $this->lockFactory->createLocker($match['key']);

            try {
                $locker->acquire();
                $targetFile = Environment::getPublicPath() . $path;
                $result = $this->imageGeneratorService->generateImage(
                    $targetFile,
                    $match['type'],
                    (int)$match['id'],
                    $match['processing'],
                    $match['key'],
                    $match['ext'],
                );

                $body = $this->streamFactory->createStreamFromResource($result->toStream());

                return $this->responseFactory->createResponse()
                    ->withBody($body)
                    ->withHeader('Content-Type', $result->mimetype());
            } catch (\Throwable $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'path' => $path]);
            } finally {
                if ($locker->isAcquired()) {
                    $locker->release();
                }
            }
        }

        return $handler->handle($request);
    }
}
