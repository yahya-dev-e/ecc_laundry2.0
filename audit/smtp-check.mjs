import net from 'node:net';
import fs from 'node:fs';
import {spawn} from 'node:child_process';
const messages=[];
const server=net.createServer(socket=>{
 let buffer='',inData=false,body=[];
 socket.setEncoding('utf8');socket.write('220 localhost audit SMTP\r\n');
 socket.on('data',chunk=>{
  buffer+=chunk;
  while(buffer.includes('\r\n')){
   const end=buffer.indexOf('\r\n'),line=buffer.slice(0,end);buffer=buffer.slice(end+2);
   if(inData){if(line==='.') {messages.push(body.join('\r\n'));body=[];inData=false;socket.write('250 captured\r\n');}else body.push(line);continue;}
   if(/^EHLO|^HELO/.test(line))socket.write('250-localhost\r\n250 SIZE 1000000\r\n');
   else if(/^DATA/.test(line)){inData=true;socket.write('354 end with dot\r\n');}
   else if(/^QUIT/.test(line)){socket.end('221 bye\r\n');}
   else socket.write('250 ok\r\n');
  }
 });
});
await new Promise(resolve=>server.listen(8766,'127.0.0.1',resolve));
const child=spawn('php',['-c','audit/.runtime/php.ini','audit/mail-check.php'],{stdio:['ignore','pipe','pipe']});
let output='';child.stdout.on('data',d=>output+=d);child.stderr.on('data',d=>output+=d);
const exitCode=await new Promise(resolve=>child.on('exit',resolve));
await new Promise(resolve=>server.close(resolve));
const report={exitCode,output,messageCount:messages.length,allRecipientsSynthetic:messages.every(m=>/To:.*student@example\.test/.test(m)),welcomeContainsDashboardLink:!!messages[0]?.includes('/dashboard'),resetContainsTokenLink:!!messages[1]?.includes('/reset-password/audit-reset-token'),deliveryScope:'localhost SMTP capture; no external inbox or deliverability tested'};
fs.writeFileSync(new URL('./evidence/smtp-results.json',import.meta.url),JSON.stringify(report,null,2));
console.log(JSON.stringify(report,null,2));
