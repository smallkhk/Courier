// A small single-series column chart: one hue, thin rounded columns from a
// shared baseline, a tooltip on hover/focus, and a table view underneath.
export function BarChart({
  title,
  points,
  format,
}: {
  title: string;
  points: { label: string; value: number }[];
  format: (v: number) => string;
}) {
  const max = Math.max(0, ...points.map((p) => p.value));
  const total = points.reduce((sum, p) => sum + p.value, 0);
  return (
    <section className="card">
      <div className="chart-head">
        <h2 style={{ margin: 0 }}>{title}</h2>
        <span className="small muted">
          Total <strong style={{ color: "var(--text)" }}>{format(total)}</strong> · peak {format(max)}
        </span>
      </div>
      <div className="bars" role="img" aria-label={`${title}. Total ${format(total)}.`}>
        {points.map((p) => (
          <div key={p.label} className="bar-slot" tabIndex={0}>
            <div className={`bar ${p.value === 0 ? "zero" : ""}`} style={{ height: max ? `${(p.value / max) * 100}%` : "2px" }} />
            <span className="bar-tip">
              {p.label}
              <br />
              <strong>{format(p.value)}</strong>
            </span>
          </div>
        ))}
      </div>
      <div className="bar-axis">
        <span>{points[0]?.label}</span>
        <span>{points[Math.floor(points.length / 2)]?.label}</span>
        <span>{points.at(-1)?.label}</span>
      </div>
      <details style={{ marginTop: 8 }}>
        <summary className="small muted">
          <span className="chev">›</span> Show as table
        </summary>
        <div className="table-wrap">
          <table>
            <tbody>
              {points.map((p) => (
                <tr key={p.label}>
                  <td>{p.label}</td>
                  <td>{format(p.value)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </details>
    </section>
  );
}
