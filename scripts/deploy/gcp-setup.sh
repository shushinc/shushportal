#!/usr/bin/env bash
# One-time GCP setup so GitHub Actions can SSH into portal-kong-runtime
# without any stored keys (Workload Identity Federation + IAP tunnel).
# Run from your laptop with an account that is Owner / IAM admin on sherlock-004:
#   bash scripts/deploy/gcp-setup.sh
# Re-running is safe; "already exists" errors are ignored.
set -uo pipefail

PROJECT=sherlock-004
INSTANCE=portal-kong-runtime
GITHUB_REPO=shushinc/shushportal
DEPLOY_BRANCH=development
SA_NAME=github-portal-deployer
POOL=github
PROVIDER=shushportal

SA="$SA_NAME@$PROJECT.iam.gserviceaccount.com"
PROJECT_NUMBER="$(gcloud projects describe "$PROJECT" --format='value(projectNumber)')"
ZONE="$(gcloud compute instances list --project "$PROJECT" --filter="name=$INSTANCE" --format='value(zone.basename())')"
[ -n "$ZONE" ] || { echo "Instance $INSTANCE not found in $PROJECT"; exit 1; }
NETWORK="$(gcloud compute instances describe "$INSTANCE" --project "$PROJECT" --zone "$ZONE" --format='value(networkInterfaces[0].network.basename())')"
VM_SA="$(gcloud compute instances describe "$INSTANCE" --project "$PROJECT" --zone "$ZONE" --format='value(serviceAccounts[0].email)')"
OSLOGIN="$(gcloud compute instances describe "$INSTANCE" --project "$PROJECT" --zone "$ZONE" --format='value(metadata.items.enable-oslogin)')"

echo "Project $PROJECT ($PROJECT_NUMBER), zone $ZONE, network $NETWORK"
echo "VM service account: ${VM_SA:-<none>}   OS Login on instance: ${OSLOGIN:-<not set>}"

step() { printf '\n==> %s\n' "$*"; }

step "Enable APIs"
gcloud services enable iamcredentials.googleapis.com iap.googleapis.com compute.googleapis.com --project "$PROJECT"

step "Deployer service account"
gcloud iam service-accounts create "$SA_NAME" --project "$PROJECT" \
  --display-name "GitHub Actions - shushportal deploy" 2>/dev/null || echo "  (exists)"

step "Workload Identity pool + GitHub OIDC provider (only $GITHUB_REPO @ $DEPLOY_BRANCH)"
gcloud iam workload-identity-pools create "$POOL" --project "$PROJECT" --location global \
  --display-name "GitHub Actions" 2>/dev/null || echo "  (pool exists)"
gcloud iam workload-identity-pools providers create-oidc "$PROVIDER" --project "$PROJECT" --location global \
  --workload-identity-pool "$POOL" \
  --issuer-uri "https://token.actions.githubusercontent.com" \
  --attribute-mapping "google.subject=assertion.sub,attribute.repository=assertion.repository,attribute.ref=assertion.ref" \
  --attribute-condition "assertion.repository=='$GITHUB_REPO' && assertion.ref=='refs/heads/$DEPLOY_BRANCH'" \
  2>/dev/null || echo "  (provider exists)"

gcloud iam service-accounts add-iam-policy-binding "$SA" --project "$PROJECT" \
  --role roles/iam.workloadIdentityUser \
  --member "principalSet://iam.googleapis.com/projects/$PROJECT_NUMBER/locations/global/workloadIdentityPools/$POOL/attribute.repository/$GITHUB_REPO" >/dev/null

step "Permissions for SSH via IAP"
gcloud projects add-iam-policy-binding "$PROJECT" --member "serviceAccount:$SA" \
  --role roles/compute.viewer --condition=None >/dev/null
gcloud projects add-iam-policy-binding "$PROJECT" --member "serviceAccount:$SA" \
  --role roles/iap.tunnelResourceAccessor --condition=None >/dev/null

if [ "$OSLOGIN" = "TRUE" ] || [ "$OSLOGIN" = "true" ]; then
  # OS Login: grant sudo-capable login on this instance only.
  gcloud compute instances add-iam-policy-binding "$INSTANCE" --project "$PROJECT" --zone "$ZONE" \
    --member "serviceAccount:$SA" --role roles/compute.osAdminLogin >/dev/null
  if [ -n "$VM_SA" ]; then
    gcloud iam service-accounts add-iam-policy-binding "$VM_SA" --project "$PROJECT" \
      --member "serviceAccount:$SA" --role roles/iam.serviceAccountUser >/dev/null
  fi
  echo "  OS Login mode: granted compute.osAdminLogin on $INSTANCE"
else
  # Metadata SSH keys (OS Login off): gcloud pushes a key into instance metadata,
  # which needs instanceAdmin on this instance. Your existing SSH access is untouched.
  gcloud compute instances add-iam-policy-binding "$INSTANCE" --project "$PROJECT" --zone "$ZONE" \
    --member "serviceAccount:$SA" --role roles/compute.instanceAdmin.v1 >/dev/null
  if [ -n "$VM_SA" ]; then
    gcloud iam service-accounts add-iam-policy-binding "$VM_SA" --project "$PROJECT" \
      --member "serviceAccount:$SA" --role roles/iam.serviceAccountUser >/dev/null
  fi
  echo "  Metadata-key mode: granted compute.instanceAdmin.v1 on $INSTANCE"
fi

step "Firewall: allow SSH from Google IAP range only"
gcloud compute firewall-rules create allow-iap-ssh-"$NETWORK" --project "$PROJECT" \
  --network "$NETWORK" --direction INGRESS --action allow --rules tcp:22 \
  --source-ranges 35.235.240.0/20 2>/dev/null || echo "  (rule exists)"

cat <<OUT

============================================================
Done. Add these in GitHub -> shushinc/shushportal -> Settings -> Secrets and variables -> Actions:

  Secret   GCP_WIF_PROVIDER = projects/$PROJECT_NUMBER/locations/global/workloadIdentityPools/$POOL/providers/$PROVIDER
  Secret   GCP_DEPLOY_SA    = $SA
  Variable GCE_ZONE         = $ZONE

Then check the VM can pull without a password (run on the VM):
  sudo -u \$(stat -c %U /var/www/html/shushportal) git -C /var/www/html/shushportal fetch origin
============================================================
OUT
