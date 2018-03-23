<?php

final class MochaEngine extends ArcanistUnitTestEngine {

    private $projectRoot;
    private $parser;

    private $mochaBin;
    private $nycBin;
    private $coverReportDir;
    private $testIncludes;

    /**
     * Determine which executables and test paths to use.
     *
     * Ensure that all of the required binaries are available for the
     * tests to run successfully.
     */
    protected function loadEnvironment() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();

        // Get config options
        $config = $this->getConfigurationManager();

        $this->mochaBin = $config->getConfigFromAnySource(
            'unit.mocha.bin.mocha',
            './node_modules/mocha/bin/mocha');

        $this->nycBin = $config->getConfigFromAnySource(
            'unit.mocha.bin.nyc',
            './node_modules/nyc/bin/nyc.js');

        $this->coverReportDir = $config->getConfigFromAnySource(
            'unit.mocha.coverage.reportdir',
            './coverage');

        $this->testIncludes = $config->getConfigFromAnySource(
            'unit.mocha.test.include',
            array('test/**/*.test.js'));

        // Make sure required binaries are available
        $binaries = array($this->mochaBin, $this->nycBin);

        foreach ($binaries as $binary) {
            if (!Filesystem::binaryExists($binary)) {
                throw new Exception(
                    pht(
                        'Unable to find binary "%s".',
                        $binary));
            }
        }
    }

    public function run() {
        $this->loadEnvironment();

        // Temporary files for holding report output
        $xunit_tmp = new TempFile();
        $cover_xml_path = $this->coverReportDir . '/clover.xml';

        if ($this->getEnableCoverage() !== false) {
            // Remove coverage report if it already exists
            if (file_exists($cover_xml_path)) {
                if (!unlink($cover_xml_path)) {
                    throw new Exception("Couldn't delete old coverage report '" . $cover_xml_path . "'");
                }
            }
        }

        $this->runTest($xunit_tmp);

        // Parse and return the xunit output
        $this->parser = new ArcanistXUnitTestResultParser();
        $results = $this->parseTestResults($xunit_tmp, $cover_xml_path);

        return $results;
    }

    protected function runTest($xunit_tmp) {
        // Create test include options list
        $include_opts = array();
        if ($this->testIncludes != null) {
            foreach ($this->testIncludes as $include_glob) {
                $include_opts[] = escapeshellarg($include_glob);
            }
        }
        $include_opts = implode(' ', $include_opts);

        if ($this->getEnableCoverage()  !== false) {
            $command = csprintf(
                '%C --all --reporter clover --report-dir %s ' .
                '%s -R xunit --reporter-options output=%s %C',
                $this->nycBin,
                $this->coverReportDir,
                $this->mochaBin,
                $xunit_tmp,
                $include_opts
            );
        }
        else {
            $command = csprintf(
                '%s -R xunit --reporter-options output=%s %C',
                $this->mochaBin,
                $xunit_tmp,
                $include_opts
            );
        }
        $cwd = getcwd();
        try {
            chdir($this->projectRoot);
            passthru($command, $exit_status);
        }
        finally {
            chdir($cwd);
        }
        if ($exit_status > 1) {
            // mocha returns 1 if tests are failing
            throw new CommandException('Exit status of ' . $exit_status, $command, null, '', '');
        }
    }

    protected function parseTestResults($xunit_tmp, $cover_xml_path) {
        $results = $this->parser->parseTestResults(Filesystem::readFile($xunit_tmp));

        if ($this->getEnableCoverage() !== false) {
            $coverage_report = $this->readCoverage($cover_xml_path);
            foreach($results as $result) {
                $result->setCoverage($coverage_report);
            }
        }

        return $results;
    }

    public function readCoverage($path) {
        $coverage_data = Filesystem::readFile($path);
        if (empty($coverage_data)) {
            return array();
        }

        $coverage_dom = new DOMDocument();
        $coverage_dom->loadXML($coverage_data);

        $reports = array();
        $classes = $coverage_dom->getElementsByTagName('class');

        $files = $coverage_dom->getElementsByTagName('file');
        foreach ($files as $file) {
            $absolute_path = $file->getAttribute('path');
            $relative_path = str_replace($this->projectRoot.'/', '', $absolute_path);

            $line_count = count(file($absolute_path));

            // Mark unused lines as N, covered lines as C, uncovered as U
            $coverage = '';
            $start_line = 1;
            $lines = $file->getElementsByTagName('line');
            for ($i = 0; $i < $lines->length; $i++) {
                $line = $lines->item($i);
                $line_number = (int)$line->getAttribute('num');
                $line_hits = (int)$line->getAttribute('count');

                $next_line = $line_number;
                for ($start_line; $start_line < $next_line; $start_line++) {
                    $coverage .= 'N';
                }

                if ($line_hits > 0) {
                    $coverage .= 'C';
                } else {
                    $coverage .= 'U';
                }

                $start_line++;
            }

            while ($start_line <= $line_count) {
                $coverage .= 'N';
                $start_line++;
            }

            $reports[$relative_path] = $coverage;
        }

        return $reports;
    }

}
