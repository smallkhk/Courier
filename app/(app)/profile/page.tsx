import { Avatar } from "@/components/Avatar";
import { LogoutButton } from "@/components/LogoutButton";
import { ProfileForm } from "@/components/ProfileForm";
import { pageUser } from "@/lib/page-auth.ts";
import { getProfile } from "@/lib/repo.ts";

export default async function ProfilePage() {
  const user = await pageUser();
  const profile = getProfile(user.accountId);
  return (
    <>
      <div className="caller-head">
        <Avatar name={profile.full_name || user.email} size="lg" />
        <div>
          <h1>My profile</h1>
          <p className="muted small">{user.email}</p>
        </div>
      </div>
      <p className="muted" style={{ marginTop: 14 }}>
        Your AI speaks as you on every call. Tell it what it needs to know, and only update this when something changes.
      </p>
      <ProfileForm initial={profile} />
      <div style={{ textAlign: "center", marginTop: 20 }}>
        <LogoutButton />
      </div>
    </>
  );
}
