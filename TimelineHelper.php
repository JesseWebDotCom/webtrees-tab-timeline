<?php

declare(strict_types=1);

namespace JesseWebDotCom\Webtrees\Module\TimelineTab;

use Fisharebest\Webtrees\Services\IndividualFactsService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;

class TimelineHelper {

    public function getTimelineContent($module, $individual) {
        // import this modules stylesheet
        $url = $module->assetUrl('css/' . $module::CUSTOM_MODULE . '.min.css');
        $timeline = '<link rel="stylesheet" href="' . $url . '">';

        // get facts helper
        $individualFactsService = new IndividualFactsService(new LinkedRecordService(), new ModuleService());
        $factsHelper = new FactsHelper($individualFactsService);

        // get facts according to settings
        $mergedFacts = $factsHelper->getMergedFacts($individual, $module->getPreference('familyfacts'), $module->getPreference('associatefacts'), $module->getPreference('relativefacts'), $module->getPreference('historicfacts'));
        
        // process facts
        foreach($mergedFacts as $fact) {

            $parent  = $fact->record();
            $tree    = $parent->tree();
            [, $tag] = explode(':', $fact->tag());
            $value   = strip_tags($fact->value());

            // get year/age for this fact
            $ageAndYear = $factsHelper->getAgeYears($individual, $fact);
            $year = $ageAndYear['year'];
            $age = $ageAndYear['year'] === '' ? '' : $ageAndYear['age'];

            // build row
            $image = '';
            if ($this->isValidTag($tag)) {
                $place = $fact->place()->shortName();
                $place = '<a href="' . e($fact->place()->url()) . '" title="' . strip_tags($fact->place()->fullName()) . '">' . $fact->place()->shortName() . '</a>';

                if ($parent !== $individual) {
                    if ($parent instanceof Family) {
                        foreach ($parent->spouses()->filter(static fn ($individual): bool => $individual !== $record) as $spouse) {
                            $value = $this->getProfileLink($spouse);
                            $image = $this->getImageWithFallback($spouse, $factsHelper);
                        }                        
                    } elseif ($parent instanceof Individual) {                                                
                        $value = $this->getProfileLink($parent);
                        $image = $this->getImageWithFallback($parent, $factsHelper);                      
                    } else {
                        if (in_array($tag, ['MARR'])) {
                            $spouse = $fact->record()->husband();
                            if ($spouse->url() === $individual->url()) {
                                $spouse = $fact->record()->wife();
                            }
                    
                            $value = $this->getProfileLink($spouse);                           
                            $image = $this->getImageWithFallback($spouse, $factsHelper);
                        }
                    
                        if ($value === 'CLOSE_RELATIVE') {
                            // name/link and image of relative
                            $value = $this->getProfileLink($fact->record());
                            $image = $this->getImageWithFallback($fact->record(), $factsHelper);
                        }
                    }
                } else {
                    // get fact image url (ex. picture of high school building)
                    $image = $factsHelper->getFactImageURL($individual, $fact, $tag);    
                }

                $image_href = $this->getValue($value, 'href');

                $content = '<div class=timeline_value>' . $value . '</div>';
                $content = $content . '<div class=timeline_date>' . $fact->date()->display($tree, null, true) . '</div>';
                $content = $content . '<div class=timeline_place>' . $place . '</div>';
                $content = '<div class=timeline_content_column>' . $content . '</div>';
                
                $timeline = $timeline . $this->getTimelineRow($fact, $tag, $year, $age, $content, $image, $image_href);
            }
        }

        return '<div class="timeline_container py-4">' . $timeline . '</div>';
    }


    // Helper function to generate profile link
    function getProfileLink($individual) {
        return '<a href="' . e($individual->url()) . '>' . $individual->fullName() . '</a>';
    }

    // Helper function to get image with fallback
    public function getImageWithFallback($individual, $factsHelper) {
        $image = $factsHelper->getImageURL($individual);
        if (method_exists($individual, 'sex')) {
            return $image === '' ? 'wt-individual-silhouette-' . strtolower($individual->sex()) : $image;
        } else {
            return "";
        }
    }    

    function getValue($phrase, $prefix) {
        $value = '';
        preg_match('/' . $prefix . '="(.*?)"/', $phrase, $matches);
        if (isset($matches[1])) {
            $value = $matches[1];
        }
    
        // hack to remove trailing slashes for values missing a trailing quote
        $value = strip_tags($value);
        $value = rtrim($value, '>');
        return $value;
    }
          
    private function getImageStyleOrContent($image, $title) {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return ['style="background-image: url(' . $image . ')"', ''];
        } else if ($image !== '') {
            return ['', '<div title="' . strip_tags($title) . '" class="icon ' . $image . '"></div>'];
        } else {
            return ['style="background-image: url()"', ''];  // default image
        }
    }
    
    public function getTimelineRow($fact, $tag, $year, $age, $content, $image, $image_href) {
        $title = $fact->label();
        $image = $image ? $image : $this->getTimelineImage($fact, $tag);
        [$imageStyle, $imageContent] = $this->getImageStyleOrContent($image, $title);
    
        $cursorStyle = $image_href ? 'onclick="window.location.href = \'' . $image_href . '\';" style="cursor: pointer;"' : '';
    
        $timelineRow = <<<HTML
            <div class="timeline_outer">
                <div class="timeline_card">
                    <div class="timeline_info">
                        <div class="timeline_year-age">
                            <span class="timeline_year">{$year}</span><br>
                            <span class="timeline_age">{$age}</span>
                        </div>
                        <div class="timeline_content">
                            <div class="timeline_text">
                                <div class="timeline_title">{$title}</div>
                                <div class="timeline_content">{$content}</div>
                            </div>
                            <div class="timeline_image" {$cursorStyle}>
                                <div class="image" {$imageStyle}>{$imageContent}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    
        return $timelineRow;
    }    

    public function isValidTag($tag) {
        $validTags = [
            'ADOP',
            'ANUL',
            'BAPL',
            'BAPM',
            'BIRT',
            'BURI',
            'CHR',
            'DEAT',
            'DIV',
            'EDUC',
            'ENGA',
            'EVEN',
            'IMMI',
            'MARB',
            'MARR',
            'NATU',
            'OCCU',
            'PROP',
            'RESI',
            'RETI',
            'SLGS',
            // 'HUSB', 'WIFE', 'CHIL', 'FAMC', 'FAMS'
        ];

        return in_array($tag, $validTags);
        // return True;
    }
    
    public function getTimelineImage($fact, $tag) {
        if ($this->isValidTag($tag)) {
            return 'wt-fact-icon-' . $tag;
        }
    
        return '';
    }      
}