import { WalletClient } from "@/components/WalletClient";
import { config, formatUsd } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { enabledNetworks } from "@/lib/payments.ts";
import { getCredit, listDeposits, listUsage } from "@/lib/repo.ts";

const KIND_LABEL: Record<string, string> = { call: "Call", deposit: "Top-up", credit: "Credit" };

export default async function WalletPage() {
  const user = await pageUser();
  const usage = listUsage(user.accountId, 30);
  return (
    <>
      <h1>Wallet</h1>
      <p className="muted">Calls cost {formatUsd(config.pricePerMinuteMicros)} per minute, charged by the second.</p>
      <WalletClient
        wallets={config.wallets}
        networks={enabledNetworks()}
        minDeposit={config.minDepositUsd}
        initialDeposits={listDeposits(user.accountId)}
        initialCredit={getCredit(user.accountId)}
      />
      <section className="card">
        <h2>Transactions and usage</h2>
        {usage.length === 0 ? (
          <p className="muted">Nothing yet.</p>
        ) : (
          <table>
            <thead>
              <tr>
                <th>When</th>
                <th>What</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              {usage.map((e) => (
                <tr key={e.id}>
                  <td>{new Date(e.created_at * 1000).toLocaleString()}</td>
                  <td>
                    {KIND_LABEL[e.kind] ?? e.kind}
                    {e.kind === "call" && ` · ${Math.floor(e.quantity / 60)}m ${e.quantity % 60}s`}
                  </td>
                  <td className={e.amount_micros < 0 ? "" : "ok"}>{formatUsd(e.amount_micros)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </>
  );
}
