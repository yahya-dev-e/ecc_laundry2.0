import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawn} from 'node:child_process';
const runtime = fs.mkdtempSync(path.join(os.tmpdir(), 'laundry-browser-'));
const database = path.join(runtime, 'fixes-browser.sqlite');
fs.writeFileSync(database, '');
const port = Number(process.env.BROWSER_TEST_PORT || 8767);
const env = {...process.env, APP_ENV:'local', APP_DEBUG:'false',
    APP_KEY:'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    APP_URL:'http://127.0.0.1:'+port, APP_TIMEZONE:'Africa/Casablanca',
    DB_CONNECTION:'sqlite', DB_DATABASE:database, SESSION_DRIVER:'database',
    SESSION_ENCRYPT:'true', CACHE_STORE:'database', QUEUE_CONNECTION:'database',
    PRIVATE_TEST_ENABLED:'false',
    MAIL_MAILER:'log', MAIL_FROM_ADDRESS:'sender@example.test',
    FIXES_BROWSER_TEST:'1', BROWSER_TEST_URL:'http://127.0.0.1:'+port,
    BROWSER_ENGINES: process.env.BROWSER_ENGINES || 'chromium,firefox,webkit',
};
const phpArgs = process.env.TEST_PHP_INI ? ['-c', process.env.TEST_PHP_INI] : [];
function run(executable,args) {
    return new Promise((resolve,reject)=>{
        const child=spawn(executable,args,{cwd:path.join(import.meta.dirname,'../..'),env,stdio:'inherit'});
        child.on('error',reject);child.on('exit',code=>code===0?resolve():reject(Error('Command exited '+code)));
    });
}
let server;
try {
    await run(process.env.PHP_BINARY || 'php',[...phpArgs,'tests/browser/seed.php']);
    server=spawn(process.env.PHP_BINARY || 'php',[...phpArgs,'-S','127.0.0.1:'+port,'-t','public','tests/browser/router.php'],{
        cwd:path.join(import.meta.dirname,'../..'),env,stdio:'ignore'
    });
    server.on('error',console.error);
    let ready=false;
    for(let i=0;i<50;i++){
        try{if((await fetch(env.APP_URL+'/up')).ok){ready=true;break;}}catch{}
        await new Promise(resolve=>setTimeout(resolve,200));
    }
    if(!ready)throw Error('Test server did not start');
    const engines = env.BROWSER_ENGINES.split(',');
    const results = [];
    const evidence = new URL('./evidence/results.json', import.meta.url);
    let failed = false;
    for (let index = 0; index < engines.length; index++) {
        // Each engine gets fresh synthetic users and rate-limit state.
        // Do not weaken the application's signup throttle to accommodate tests.
        if (index > 0) await run(process.env.PHP_BINARY || 'php',[...phpArgs,'tests/browser/seed.php']);
        env.BROWSER_ENGINES = engines[index];
        try { await run(process.execPath,['tests/browser/regression.mjs']); }
        catch { failed = true; }
        const engineResults = JSON.parse(fs.readFileSync(evidence, 'utf8'));
        results.push(...engineResults);
    }
    fs.writeFileSync(evidence, JSON.stringify(results,null,2));
    if (failed) throw new Error('One or more browser workflows failed; inspect tests/browser/evidence/results.json');
} finally {
    if(server){server.kill();await new Promise(resolve=>{server.once('exit',resolve);setTimeout(resolve,3000);});}
    fs.rmSync(runtime,{recursive:true,force:true});
}
