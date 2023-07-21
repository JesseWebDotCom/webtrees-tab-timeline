<?php
// This file contains generic code to create a tab module

declare(strict_types=1);

namespace JesseWebDotCom\Webtrees\Module\TimelineTab;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Localization\Translation;

require 'FactsHelper.php';
require 'TimelineHelper.php';

/**
 * Class TimelineTabModule
 */
class TimelineTabModule extends AbstractModule implements ModuleTabInterface, ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleTabTrait;
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    /**
     * list of const for module administration
     */
    public const CUSTOM_TITLE       = 'Timeline';
    public const CUSTOM_MODULE      = 'webtrees-tab-timeline';
    public const CUSTOM_DESCRIPTION = 'A webtrees timeline tab for an individual (similar to Ancestry or FamilySearch)';
    public const CUSTOM_AUTHOR      = 'JesseWebDotCom';
    public const CUSTOM_WEBSITE     = 'https://github.com/JesseWebDotCom/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION     = '0.0.1';
    public const CUSTOM_LAST        = 'https://github.com/JesseWebDotCom/' .
                                      self::CUSTOM_MODULE. '/releases';

    private $linked_record_service;
    public function getAdminAction(): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'familyfacts' => $this->getPreference('familyfacts', '1'),
            'associatefacts' => $this->getPreference('associatefacts', '1'),
            'relativefacts' => $this->getPreference('relativefacts', '1'),
            'historicfacts' => $this->getPreference('historicfacts', '0'),

            'title'        => $this->title()
        ]);
    }

    // Save the user preference.
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        if ($params['save'] === '1') {

            // print_r($params);
            $this->setPreference('familyfacts', $params['familyfacts'] ?? '0');
            $this->setPreference('associatefacts', $params['associatefacts'] ?? '0');
            $this->setPreference('relativefacts', $params['relativefacts'] ?? '0');
            $this->setPreference('historicfacts', $params['historicfacts'] ?? '0');

            $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
            FlashMessages::addMessage($message, 'success');

        }

        return redirect($this->getConfigLink());
    }

    public function title(): string
    {
        return I18N::translate(self::CUSTOM_TITLE);
    }

    public function description(): string
    {
        return I18N::translate(self::CUSTOM_DESCRIPTION);
    }

    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }
    
    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
    }

    public function defaultTabOrder(): int
    {
        return -1; // ensure tab is first
    }

    public function hasTabContent(Individual $individual): bool
    {
        return true;
    }

    public function isGrayedOut(Individual $individual): bool
    {
        return false;
    }

    /** {@inheritdoc} */
    public function getTabContent(Individual $individual): string
    {
        $timelineHelper = new TimelineHelper();
        return $timelineHelper->getTimelineContent($this, $individual);
    }   

    /** {@inheritdoc} */
    public function canLoadAjax(): bool
    {
        return false;
    }

    public function __construct()
    {
        // IMPORTANT - the constructor is called on *all* modules, even ones that are disabled.
        // It is also called before the webtrees framework is initialised, and so other components will not yet exist.
    }

    public function boot(): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
    
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $extensions = array('mo', 'po');
        foreach ($extensions as &$extension) {
            $file       = $lang_dir . $language . '.' . $extension;
            if (file_exists($file)) {
                return (new Translation($file))->asArray();
            }
        }
        return [];
    }
}
return new TimelineTabModule;

