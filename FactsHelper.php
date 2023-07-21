<?php

declare(strict_types=1);

namespace JesseWebDotCom\Webtrees\Module\TimelineTab;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Illuminate\Support\Collection;
use Fisharebest\Webtrees\Services\IndividualFactsService;

use Fisharebest\Webtrees\Age;

class FactsHelper {
    private IndividualFactsService $individualFactsService;

    public function __construct(IndividualFactsService $individualFactsService) {
        $this->individualFactsService = $individualFactsService;
    }

    public function getIndividualFacts(Individual $individual, Collection $exclude_facts): Collection {
        return $this->individualFactsService->individualFacts($individual, $exclude_facts);
    }

    public function getFamilyFacts(Individual $individual, Collection $exclude_facts): Collection {
        return $this->individualFactsService->familyFacts($individual, $exclude_facts);
    }

    public function getAssociateFacts(Individual $individual): Collection {
        return $this->individualFactsService->associateFacts($individual);
    }

    public function getRelativeFacts(Individual $individual): Collection {
        return $this->individualFactsService->relativeFacts($individual);
    }

    public function getHistoricFacts(Individual $individual): Collection {
        return $this->individualFactsService->historicFacts($individual);
    }    

    public function getMergedFacts(Individual $individual, string $includeFamily = "1", string $includeAssociate = "1", string $includeRelative = "1", string $includeHistoric = "1"): Collection {

        // Convert the options to boolean values
        $includeFamily = filter_var($includeFamily, FILTER_VALIDATE_BOOLEAN);
        $includeAssociate = filter_var($includeAssociate, FILTER_VALIDATE_BOOLEAN);
        $includeHistoric = filter_var($includeHistoric, FILTER_VALIDATE_BOOLEAN);
        $includeRelative = filter_var($includeRelative, FILTER_VALIDATE_BOOLEAN);
    
        // Get individual, family, associate, and historic facts
        $individual_facts = $this->individualFactsService->individualFacts($individual, collect());
        
        $family_facts = $includeFamily ? $this->individualFactsService->familyFacts($individual, collect()) : collect();
        $associate_facts = $includeAssociate ? $this->individualFactsService->associateFacts($individual) : collect();
        $historic_facts = $includeHistoric ? $this->individualFactsService->historicFacts($individual) : collect();
        $relative_facts = $includeRelative ? $this->individualFactsService->relativeFacts($individual) : collect();
    
        // Merge all the facts together
        $merged_facts = $individual_facts
            ->merge($family_facts)
            ->merge($relative_facts)
            ->merge($associate_facts)
            ->merge($historic_facts);         
    
        // Sort the merged facts
        $sorted_facts = Fact::sortFacts($merged_facts);
    
        return $sorted_facts;
    }
    
    /**
     * Get age and year based on given parameters.
     *
     * @param Individual $individual
     * @param Fact $fact
     *
     * @return array<string, string> The calculated age and year
     */
    public function getAgeYears(Individual $individual, Fact $fact): array {
        $parent = $fact->record();
        $tree = $parent->tree();

        $MAX_ALIVE_AGE = (int)$tree->getPreference('MAX_ALIVE_AGE');
        $age = 0;
        $ageYear = 0;
        $minYear = $fact->date()->minimumDate()->year();
        $maxYear = $fact->date()->maximumDate()->year();
        
        if ($individual->tag() === Individual::RECORD_TYPE && $individual->getBirthDate()->isOK()) {
            $age = new Age($individual->getBirthDate(), $fact->date());
            $age = $age->ageYears();
            $ageYear = $individual->getBirthDate()->gregorianYear();
        }

        $yearAge = $this->calculateYearAge($age, $ageYear, $minYear, $maxYear, $MAX_ALIVE_AGE);
        
        [, $tag] = explode(':', $fact->tag());
        if ($tag === 'BIRT') {
            $yearAge['age'] = '(AGE)';
        }

        return $yearAge;
    }

    /**
     * Calculates the year and age based on the given parameters.
     *
     * @param int $age The age
     * @param int $ageYear The age year
     * @param int $minYear The minimum year
     * @param int $maxYear The maximum year
     * @param int $MAX_ALIVE_AGE The max age
     *
     * @return array<string, string> The calculated year and age
     */
    private function calculateYearAge(int $age, int $ageYear, int $minYear, int $maxYear, int $MAX_ALIVE_AGE): array {
        $year = '?';
        $minYear = $minYear ?: 0;
        $maxYear = $maxYear ?: 0;
        if (!$minYear && !$maxYear) {
            $age = 0;
            $year = '';
        } elseif (!$minYear && $maxYear) {
            $age = max($maxYear - $ageYear, 0);
            $year = $maxYear;
        } elseif ($minYear && !$maxYear) {
            $age = max($minYear - $ageYear, 0);
            $year = $minYear;   
        } elseif ($minYear === $maxYear) {
            $year = $maxYear;
            $age = max($maxYear - $ageYear, 0);
        } else {
            $year = $minYear;
            $age = max($age - ($maxYear - $minYear), 0);
        }
        if ($age > $MAX_ALIVE_AGE) {
            $age = 0;
        }

        return ['age' => (string)$age, 'year' => (string)$year];
    }

    public function getFactImageURL(Individual $individual, Fact $fact, string $tag) {
        $URL = '';
        if (preg_match_all('/\n(2 OBJE\b.*(?:\n[^2].*)*)/', $fact->gedcom(), $matches, PREG_SET_ORDER) > 0) {
            // Get the first match
            $match = $matches[0];
        
            // Extract the src attribute value from the matched code
            $code = view('fact-gedcom-fields', ['gedcom' => $match[1], 'parent' => $fact->tag(), 'tree' => $fact->record()->tree()]);
            $URL = $this->getNodeValue($code, 'src');
        }

        // show individual's image if its their birth fact
        if ($tag === 'BIRT' && $URL === '') {
            $URL = $this->getImageURL($individual);
        }  

        return $URL;
    }

    public function getImageURL($entity) {
        if (method_exists($entity, 'displayImage')) {
            $code = $entity->displayImage(100, 100, 'crop', ['class' => 'wt-chart-box-thumbnail']);
            return $this->getNodeValue($code, 'src');
        } else {
            return "";
        }
    }    

    private function getNodeValue($phrase, $prefix) {
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
}
