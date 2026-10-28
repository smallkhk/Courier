// Credits a top-up that is waiting for review (amount didn't match exactly).
// Check the sender with the customer first. Usage:
//   npm run approve-deposit -- <deposit-id>
import { approveReview } from "../lib/payments.ts";

const [id] = process.argv.slice(2);
if (!id) {
  console.error("Usage: npm run approve-deposit -- <deposit-id>");
  process.exit(1);
}
approveReview(id);
console.log("Credited.");
