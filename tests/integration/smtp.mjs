import net from 'node:net';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const messages = [];
const server = net.createServer(socket => {
    let buffer='', data=false, body=[];
    socket.setEncoding('utf8'); socket.write('220 localhost integration SMTP\r\n');
    socket.on('data', chunk => {
        buffer+=chunk;
        while(buffer.includes('\r\n')) {
            const end=buffer.indexOf('\r\n'), line=buffer.slice(0,end); buffer=buffer.slice(end+2);
            if(data) { if(line==='.') {messages.push(body.join('\r\n'));body=[];data=false;socket.write('250 accepted\r\n');} else body.push(line); continue; }
            if(/^EHLO|^HELO/.test(line))socket.write('250-localhost\r\n250 SIZE 1000000\r\n');
            else if(/^DATA/.test(line)){data=true;socket.write('354 send data\r\n');}
            else if(/^QUIT/.test(line))socket.end('221 bye\r\n');
            else socket.write('250 ok\r\n');
        }
    });
});
await new Promise(resolve=>server.listen(8768,'127.0.0.1',resolve));
const args = [...(process.env.TEST_PHP_INI ? ['-c', process.env.TEST_PHP_INI] : []), 'tests/integration/mail.php'];
const child=spawn(process.env.PHP_BINARY || 'php', args, {stdio:'inherit'});
const exit=await new Promise(resolve=>child.on('exit',resolve));
await new Promise(resolve=>server.close(resolve));
assert.equal(exit,0);
assert.equal(messages.length,3);
assert.ok(messages.every(m=>/To:.*student@example\.test/.test(m)));
assert.ok(messages.some(m=>m.includes('https://laundry.school.test/dashboard')));
assert.ok(messages.some(m=>m.includes('https://laundry.school.test/reset-password/')));
console.log('PASS: Three real templates accepted by localhost SMTP; synthetic recipient only. External inbox delivery was not tested.');
