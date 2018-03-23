arc-unit-mocha
==============

Arcanist unit test engine for running [Mocha](https://www.npmjs.com/package/mocha) tests with
[nyc](https://www.npmjs.com/package/nyc) for coverage reports.

## Installation

1. Copy the library to your project's root directory. A convenient way to do this is by
 adding this repository as a submodule.

 ```console
 $ git submodule add https://github.com/Joris-van-der-Wel/arc-unit-mocha.git .arc-unit-mocha
 $ git submodule update --init
 ```

2. Load the library and set the test engine by adding these lines to your `.arcconfig`:
 ```json
 "load": [
     ".arc-unit-mocha/src/"
 ],

 "unit.engine": "MochaEngine"
 ```

3. Run `arc unit` to run tests.

## Configuration

The following options can be set in `.arcconfig`:

| Key                             | Default value                          | Description                                                   |
| ------------------------------- | -------------------------------------- | ------------------------------------------------------------- |
| `unit.mocha.bin.mocha`          | `"./node_modules/mocha/bin/mocha"`     | Path used to invoke `mocha`.                                  |
| `unit.mocha.bin.nyc`            | `"./node_modules/nyc/bin/nyc.js"`      | Path used to invoke `nyc`.                                    |
| `unit.mocha.coverage.reportdir` | `"./coverage"`                         | Path to the directory where `nyc` should output reports.      |
| `unit.mocha.include`            | `["test/**/*.test.js"]`                | An array of paths to include for the mocha tests. Globs are passed to mocha, not your shell |
