<?php
namespace Topwire\EventListener;

use Topwire\ContentObject\Exception\InvalidTableContext;
use Topwire\Context\TopwireContext;
use Topwire\Context\TopwireContextFactory;
use Topwire\Turbo\Frame;
use Topwire\Turbo\FrameOptions;
use Topwire\Turbo\FrameRenderer;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\Event\AfterStdWrapFunctionsExecutedEvent;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

#[AsEventListener('topwire.contentElementWrap')]
class ContentElementWrapListener
{
    /**
     * @param string $content
     * @param array<mixed> $configuration
     * @return string
     */
    public function __invoke(AfterStdWrapFunctionsExecutedEvent $event): void
    {
        if (!$event->getContentObjectRenderer()->stdWrapValue('turboFrameWrap', $event->getConfiguration(), 0)) {
            return;
        }
        if ($event->getContentObjectRenderer()->getRequest()->getAttribute('topwire') instanceof TopwireContext) {
            // Frame wrap is done by TOPWIRE content object automatically
            return;
        }
        if ($event->getContentObjectRenderer()->getCurrentTable() !== 'tt_content') {
            // Frame wrap is only used for the table "tt_content"
            return;
        }
        $controller = $event->getContentObjectRenderer()->getTypoScriptFrontendController();
        assert($controller instanceof TypoScriptFrontendController);

        $path = $configuration['turboFrameWrap.']['path'] ?? $this->determineRenderingPath($controller, $event->getContentObjectRenderer(), $event->getConfiguration());
        $record = $event->getContentObjectRenderer()->data;

        $contextFactory = new TopwireContextFactory($controller);
        $context = $contextFactory->forPath($path, $event->getContentObjectRenderer()->currentRecord);
        $scopeFrame = (bool)$event->getContentObjectRenderer()->stdWrapValue('scopeFrame', $event->getConfiguration()['turboFrameWrap.'] ?? [], 1);
        $frameId = $event->getContentObjectRenderer()->stdWrapValue('frameId', $event->getConfiguration()['turboFrameWrap.'] ?? [], $record['tx_topwire_frame_id'] ?? '');
        $frame = new Frame(
            baseId: (string)($frameId ?: $event->getContentObjectRenderer()->currentRecord),
            wrapResponse: true,
            scope: $scopeFrame ? $context->scope : null,
        );
        $showWhenFrameMatches = (bool)$event->getContentObjectRenderer()->stdWrapValue('showWhenFrameMatches', $event->getConfiguration()['turboFrameWrap.'] ?? [], false);
        $requestedFrame = $event->getContentObjectRenderer()->getRequest()->getAttribute('topwireFrame');
        if ($scopeFrame
            && $showWhenFrameMatches
            && (
                !$requestedFrame instanceof Frame
                || $requestedFrame->id !== $frame->id
            )
        ) {
            return;
        }
        $context = $context->withAttribute('frame', $frame);
        $event->setContent((new FrameRenderer())->render(
            frame: $frame,
            content: $event->getContent(),
            options: new FrameOptions(
                propagateUrl: (bool)$event->getContentObjectRenderer()->stdWrapValue('propagateUrl', $event->getConfiguration()['turboFrameWrap.'] ?? [], 0),
                morph: (bool)$event->getContentObjectRenderer()->stdWrapValue('morph', $event->getConfiguration()['turboFrameWrap.'] ?? [], 0),
            ),
            context: $scopeFrame ? $context : null,
        ));
    }

    /**
     * @param array<mixed> $configuration
     * @throws InvalidTableContext
     * @throws ContentRenderingException
     */
    private function determineRenderingPath(TypoScriptFrontendController $controller, ContentObjectRenderer $cObj, array $configuration): string
    {
        $frontendTypoScript = $cObj->getRequest()->getAttribute('frontend.typoscript');
        $setup = $frontendTypoScript?->getSetupArray();
        if (!isset($setup['tt_content'], $configuration['turboFrameWrap.'])) {
            throw new InvalidTableContext('"stdWrap.turboFrameWrap" can only be used for table "tt_content", typoscript setup missing!', 1687873940);
        }
        $frameWrapConfig = $configuration['turboFrameWrap.'];
        $paths = [
            'tt_content.',
            'tt_content./' . $cObj->data['CType'] . '.',
            'tt_content./' . $cObj->data['CType'] . './20.',
        ];
        if ($cObj->data['CType'] === 'list') {
            $paths[] = 'tt_content./' . $cObj->data['CType'] . './20./' . $cObj->data['list_type'] . '.';
        }
        foreach ($paths as $path) {
            try {
                $potentialWrapConfig = ArrayUtility::getValueByPath($setup, $path . '/stdWrap./turboFrameWrap.');
                if ($potentialWrapConfig === $frameWrapConfig) {
                    return rtrim(str_replace('./', '.', $path), '.');
                }
            } catch (MissingArrayPathException $e) {
                $potentialWrapConfig = [];
            }
        }
        return 'tt_content';
    }
}
