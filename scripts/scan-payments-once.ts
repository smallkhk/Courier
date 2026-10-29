// Checks the blockchains for USDT top-ups once and exits. For hosting that
// can't keep a background process running (e.g. cPanel): run it from a
// Cron Job every minute. Elsewhere, `npm run watch-payments` loops instead.
import { scanPayments } from "../lib/payments.ts";

const { credited, errors } = await scanPayments();
if (credited) console.log(new Date().toISOString(), `credited ${credited} top-up(s)`);
for (const error of errors) console.warn(new Date().toISOString(), error);
