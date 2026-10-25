const { test } = require("node:test");
const assert = require("node:assert/strict");
const { spawnSync } = require("node:child_process");
const { mkdtempSync, rmSync } = require("node:fs");
const { tmpdir } = require("node:os");
const path = require("node:path");

test("Passenger starts the HTTP server when it requires the startup file", () => {
  const dataDir = mkdtempSync(path.join(tmpdir(), "raero-passenger-"));
  try {
    const result = spawnSync(
      process.execPath,
      [
        "-e",
        `
      global.PhusionPassenger = {};
      const assert = require('node:assert/strict');
      let calls = 0;
      require('node:http').Server.prototype.listen = function (address) {
        assert.equal(address, 'passenger');
        calls++;
        return this;
      };
      require('./server.js');
      assert.equal(calls, 1, 'Passenger must start listening even when require.main differs');
    `,
      ],
      {
        cwd: path.resolve(__dirname, ".."),
        env: { ...process.env, DATA_DIR: dataDir },
        encoding: "utf8",
        timeout: 10000,
      },
    );
    assert.equal(result.status, 0, result.stderr || result.error?.message);
  } finally {
    rmSync(dataDir, { recursive: true, force: true });
  }
});
