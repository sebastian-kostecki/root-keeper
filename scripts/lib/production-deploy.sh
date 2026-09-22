#!/usr/bin/env bash

PRODUCTION_BRANCH="${PRODUCTION_BRANCH:-main}"

production_assert_branch() {
    local current_branch
    current_branch="$(git rev-parse --abbrev-ref HEAD)"

    if [[ "${current_branch}" != "${PRODUCTION_BRANCH}" ]]; then
        echo "ERROR: Production deploy must run on branch '${PRODUCTION_BRANCH}', currently on '${current_branch}'." >&2
        echo "Run: git checkout ${PRODUCTION_BRANCH}" >&2
        exit 1
    fi
}

production_pull_latest() {
    echo "==> Fetching and pulling ${PRODUCTION_BRANCH}..."
    git fetch origin "${PRODUCTION_BRANCH}"
    production_assert_branch
    git pull --ff-only origin "${PRODUCTION_BRANCH}"
}

# Reads the last occurrence of KEY= in an env file without sourcing it,
# stripping surrounding single or double quotes from the value.
_production_env_value() {
    local env_file="$1" key="$2"
    local line value

    line="$( { grep -E "^${key}=" "${env_file}" || true; } | tail -n1)"
    value="${line#*=}"

    if [[ "${value}" =~ ^\"(.*)\"$ ]]; then
        value="${BASH_REMATCH[1]}"
    elif [[ "${value}" =~ ^\'(.*)\'$ ]]; then
        value="${BASH_REMATCH[1]}"
    fi

    printf '%s' "${value}"
}

_production_is_blank() {
    local value="$1"
    local lower="${value,,}"

    [[ -z "${value}" || "${lower}" == "null" ]]
}

_production_is_truthy() {
    local value="$1"
    local lower="${value,,}"

    case "${lower}" in
        true | 1 | yes | on)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

production_assert_env_ready() {
    local env_file="$1"
    local key value

    for key in BACKUP_ARCHIVE_PASSWORD MAIL_HOST BACKUP_NOTIFICATION_MAIL; do
        value="$(_production_env_value "${env_file}" "${key}")"
        if _production_is_blank "${value}"; then
            echo "ERROR: ${key} is empty in ${env_file}. Set it before deploying." >&2
            exit 1
        fi
    done

    value="$(_production_env_value "${env_file}" "BACKUP_OFFSITE_ENABLED")"
    if _production_is_truthy "${value}"; then
        echo "ERROR: BACKUP_OFFSITE_ENABLED is enabled in ${env_file}. Offsite storage is not ready; set it to false." >&2
        exit 1
    fi
}

production_backup_before_migrate() {
    local compose_cmd="$1"

    if [[ -z "$(${compose_cmd} ps --status running --quiet app)" ]]; then
        echo "==> app service is not running yet, skipping pre-migration backup."
        return 0
    fi

    echo "==> Backing up database before migration..."
    ${compose_cmd} exec -T app php artisan backup:run --only-db
}
