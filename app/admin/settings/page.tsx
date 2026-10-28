import { SettingsForm } from "@/components/admin/SettingsForm";
import { allSettings } from "@/lib/settings.ts";

export default function AdminSettings() {
  return (
    <>
      <h1>Settings</h1>
      <p className="muted">Changes apply to the whole site as soon as you save. Every change is recorded in the activity log.</p>
      <SettingsForm settings={allSettings()} />
    </>
  );
}
