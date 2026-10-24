#!/bin/sh
# Deploy Community League di VPS. Dijalankan dari PC (token GHCR lewat stdin, tidak pernah
# disimpan di server):
#   gh auth token | ssh <vps> 'cd ~/communityleague && ./deploy.sh'
# 1. tarik image terbaru dari GHCR pakai DOCKER_CONFIG sementara -- login registry tidak
#    menyentuh ~/.docker milik user server (dipakai bareng proyek lain);
# 2. migrasi dijalankan di container sekali-pakai SEBELUM app diganti;
# 3. app di-recreate (downtime ±10 detik -- port di-bind langsung, tanpa nginx di depannya);
# 4. tunggu healthcheck /up; gagal -> DEPLOY_EXIT=1 dan log terakhir dicetak;
# 5. hanya image lama milik CL yang dibersihkan, image proyek lain tidak disentuh.
set -eu
cd "$(dirname "$0")"
C="docker compose"
IMAGE="ghcr.io/imagaa/communityleague:$(grep -E '^IMAGE_TAG=' .env 2>/dev/null | cut -d= -f2 || true)"
[ "$IMAGE" = "ghcr.io/imagaa/communityleague:" ] && IMAGE="ghcr.io/imagaa/communityleague:main"

echo "== pull $IMAGE"
TOKEN=$(cat)
[ -n "$TOKEN" ] || { echo "token GHCR kosong -- jalankan lewat: gh auth token | ssh ... ./deploy.sh"; echo DEPLOY_EXIT=1; exit 1; }
old=$(docker image inspect -f '{{.Id}}' "$IMAGE" 2>/dev/null || true)
DC=$(mktemp -d)
trap 'rm -rf "$DC"' EXIT
echo "$TOKEN" | DOCKER_CONFIG="$DC" docker login ghcr.io -u "${GHCR_USER:-Imagaa}" --password-stdin >/dev/null
DOCKER_CONFIG="$DC" docker pull -q "$IMAGE"
rm -rf "$DC"

echo "== db & redis"
$C up -d --wait db redis   # --wait: migrasi baru jalan setelah healthcheck db lolos

echo "== migrate"
$C run --rm --no-deps -T --entrypoint php app artisan migrate --force

echo "== app"
$C up -d --no-deps app
new=$($C ps -q app)
i=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$new")" = healthy ]; do
    i=$((i + 1))
    if [ "$i" -gt 36 ] || [ "$(docker inspect -f '{{.State.Status}}' "$new")" != running ]; then
        echo "app tidak sehat dalam 3 menit"
        docker logs --tail 40 "$new" || true
        echo DEPLOY_EXIT=1
        exit 1
    fi
    sleep 5
done

if [ -n "$old" ] && [ "$old" != "$(docker image inspect -f '{{.Id}}' "$IMAGE")" ]; then
    docker rmi "$old" >/dev/null 2>&1 || true
fi
echo DEPLOY_EXIT=0
