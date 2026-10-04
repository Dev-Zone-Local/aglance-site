import Licenses from "../../components/Licenses";
import { PageHeader } from "../../components/ag";

export default function LicencesPage() {
  return (
    <div data-testid="licences-page">
      <PageHeader eyebrow="Licences" title="Management Console licences">
        Each licence activates one Management Console. Create a key, enter the emailed code, then paste the key into the installer.
      </PageHeader>
      <div className="-mt-10">
        <Licenses />
      </div>
    </div>
  );
}
