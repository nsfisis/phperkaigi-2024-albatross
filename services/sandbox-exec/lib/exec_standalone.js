import { readFileSync } from 'node:fs';
import PHPWasm from './php-wasm.js'

const userCode = `
while ($l = fgets(STDIN)) {
  echo $l, PHP_EOL;
}
`;

const PRELUDE = `
define('STDIN', fopen('php://stdin', 'r'));
define('STDOUT', fopen('php://stdout', 'r'));
define('STDERR', fopen('php://stderr', 'r'));

`;
const BUFFER_MAX = 1024 * 1024 * 1;

let stdinPos = 0; // bytewise
let stdinBuf = Buffer.from(readFileSync('/dev/stdin'));
let stdoutPos = 0; // bytewise
let stdoutBuf = Buffer.alloc(BUFFER_MAX);
let stderrPos = 0; // bytewise
let stderrBuf = Buffer.alloc(BUFFER_MAX);

const { ccall } = await PHPWasm({
  stdin: () => {
    if (stdinBuf.length <= stdinPos) {
      return null;
    }
    return stdinBuf.readUInt8(stdinPos++);
  },
  stdout: (asciiCode) => {
    if (asciiCode === null) {
      return; // flush
    }
    if (BUFFER_MAX <= stdoutPos) {
      return; // ignore
    }
    if (asciiCode < 0) {
      asciiCode += 256;
    }
    stdoutBuf.writeUInt8(asciiCode, stdoutPos++);
  },
  stderr: (asciiCode) => {
    if (asciiCode === null) {
      return; // flush
    }
    if (BUFFER_MAX <= stderrPos) {
      return; // ignore
    }
    if (asciiCode < 0) {
      asciiCode += 256;
    }
    stderrBuf.writeUInt8(asciiCode, stderrPos++);
  },
});

const result = ccall(
  'php_wasm_run',
  'number', ['string'],
  [PRELUDE + userCode],
);
console.log({
  status: result === 0 ? 'AC' : 'RE',
  stdout: stdoutBuf.subarray(0, stdoutPos).toString(),
  stderr: stderrBuf.subarray(0, stderrPos).toString(),
});
