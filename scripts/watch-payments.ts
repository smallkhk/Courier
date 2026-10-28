// Background payment watcher. Run alongside the web app in production:
//   npm run watch-payments
import { scanPayments } from "../lib/payments.ts";

const INTERVAL_MS = 20_000;

for (;;) {
  try {
    const { credited, errors } = await scanPayments();
    if (credited) console.log(new Date().toISOString(), `credited ${credited} top-up(s)`);
    for (const error of errors) console.warn(new Date().toISOString(), error);
  } catch (err) {
    console.error(new Date().toISOString(), err);
  }
  await new Promise((resolve) => setTimeout(resolve, INTERVAL_MS));
}
