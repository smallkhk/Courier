import { ProfileForm } from "@/components/ProfileForm";
import { pageUser } from "@/lib/page-auth.ts";
import { getProfile } from "@/lib/repo.ts";

export default async function ProfilePage() {
  const user = await pageUser();
  return (
    <>
      <h1>My profile</h1>
      <p className="muted">Your AI speaks as you on every call. Tell it what it needs to know. Edit this only when something changes.</p>
      <ProfileForm initial={getProfile(user.accountId)} />
    </>
  );
}
