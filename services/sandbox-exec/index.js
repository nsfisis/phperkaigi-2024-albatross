import { fork } from 'node:child_process'
import { serve } from '@hono/node-server'
import { Hono } from 'hono'

const execPhp = (code, input, timeoutMsec) => {
  return new Promise((resolve, _reject) => {
    const proc = fork('./lib/exec.js');

    proc.send({ code, input });

    proc.on('message', (result) => {
      resolve(result);
      proc.kill();
    });

    setTimeout(() => {
      resolve({
        status: 'TLE',
        stdout: '',
        stderr: `Time Limit Exceeded: ${timeoutMsec} msec`,
      });
      proc.kill();
    }, timeoutMsec);
  });
};

const app = new Hono();

app.post('/exec', async (c) => {
  const { code, input, timeout } = await c.req.json();
  const result = await execPhp(code, input, timeout);
  return c.json(result);
});

serve({
  fetch: app.fetch,
  port: 8888,
})
