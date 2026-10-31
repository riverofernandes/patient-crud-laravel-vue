<template>
  <section class="agenda">
    <header class="page-header">
      <h1 class="page-title" tabindex="-1">Agenda</h1>
      <div class="week-nav" role="group" aria-label="Navegação entre semanas">
        <b-button
          variant="outline-primary"
          class="week-nav__btn"
          aria-label="Semana anterior"
          title="Semana anterior"
          @click="goToWeek(prevWeek)"
        >
          <b-icon-chevron-left aria-hidden="true" />
        </b-button>
        <b-button
          variant="outline-primary"
          class="week-nav__today"
          :aria-current="isCurrentWeek ? 'date' : null"
          @click="goToWeek(currentWeek)"
        >
          Hoje
        </b-button>
        <b-button
          variant="outline-primary"
          class="week-nav__btn"
          aria-label="Próxima semana"
          title="Próxima semana"
          @click="goToWeek(nextWeek)"
        >
          <b-icon-chevron-right aria-hidden="true" />
        </b-button>
      </div>
    </header>

    <div class="app-card">
      <div class="agenda-toolbar">
        <p class="agenda-week">
          <b-icon-calendar-week aria-hidden="true" class="mr-2" />
          Semana de {{ weekLabel }}
        </p>

        <div class="agenda-filters">
          <fieldset class="status-filter">
            <legend class="agenda-filters__label">Status</legend>
            <div class="status-filter__options">
              <label
                v-for="option in statusOptions"
                :key="option.value"
                class="status-chip"
                :class="[
                  `status-chip--${option.value}`,
                  { 'is-checked': statuses.includes(option.value) },
                ]"
              >
                <input
                  type="checkbox"
                  class="status-chip__input"
                  :value="option.value"
                  :checked="statuses.includes(option.value)"
                  @change="toggleStatus(option.value)"
                />
                {{ option.label }}
              </label>
            </div>
          </fieldset>

          <div class="professional-filter">
            <label for="agenda-professional" class="agenda-filters__label"
              >Profissional</label
            >
            <div class="professional-filter__field">
              <b-icon-search
                class="professional-filter__icon"
                aria-hidden="true"
              />
              <b-form-input
                id="agenda-professional"
                v-model="professional"
                type="search"
                placeholder="Filtrar por nome"
                autocomplete="off"
                @input="onProfessionalInput"
              />
            </div>
          </div>
        </div>
      </div>

      <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
        {{ announcement }}
      </p>

      <div v-if="error" class="state-box" role="alert">
        <p class="state-box__title">Não foi possível carregar a agenda</p>
        <p class="mb-3">{{ error }}</p>
        <b-button variant="primary" @click="fetch">Tentar novamente</b-button>
      </div>

      <div v-else class="agenda-days" :aria-busy="loading ? 'true' : undefined">
        <div v-if="loading && !loaded" class="state-box">
          <b-spinner variant="primary" class="mb-2" />
          <p class="mb-0">Carregando agenda...</p>
        </div>
        <template v-else>
          <section
            v-for="day in days"
            :key="day.date"
            class="agenda-day"
            :class="{
              'is-today': day.isToday,
              'is-empty': day.items.length === 0,
            }"
            :aria-labelledby="`agenda-day-${day.date}`"
          >
            <h2 :id="`agenda-day-${day.date}`" class="agenda-day__title">
              {{ dayHeading(day.date) }}
              <span v-if="day.isToday" class="today-tag">Hoje</span>
              <span v-if="day.items.length" class="agenda-day__count">
                {{ countLabel(day.items.length) }}
              </span>
            </h2>
            <p v-if="day.items.length === 0" class="agenda-day__empty">
              Nenhum atendimento
            </p>
            <ul v-else class="agenda-items">
              <li
                v-for="item in day.items"
                :id="`agenda-item-${item.id}`"
                :key="item.id"
                class="agenda-item"
                tabindex="-1"
              >
                <div class="agenda-item__time">
                  <b-icon-clock aria-hidden="true" class="mr-1" />
                  {{ rangeOf(item) }}
                </div>
                <div class="agenda-item__main">
                  <router-link
                    v-if="patientIdOf(item)"
                    class="agenda-item__patient"
                    :to="{
                      name: 'patients.show',
                      params: { id: patientIdOf(item) },
                    }"
                    >{{ patientNameOf(item) }}</router-link
                  >
                  <span v-else class="agenda-item__patient">{{
                    patientNameOf(item)
                  }}</span>
                  <span class="agenda-item__professional">
                    {{ item.professional_name }}
                  </span>
                </div>
                <div class="agenda-item__status">
                  <StatusBadge :status="item.status" sr-prefix="Status:" />
                  <router-link
                    class="agenda-item__open"
                    :to="{
                      name: 'consultations.show',
                      params: { id: String(item.id) },
                    }"
                    :aria-label="`Ver atendimento de ${patientNameOf(
                      item
                    )} às ${startOf(item)}`"
                    >Ver atendimento</router-link
                  >
                </div>
                <ConsultationStatusActions
                  class="agenda-item__actions"
                  :consultation="item"
                  small
                  :context="`de ${patientNameOf(item)} às ${startOf(item)}`"
                  :focus-target="`#agenda-item-${item.id}`"
                  @changed="onChanged"
                  @settled="onSettled"
                  @stale="fetch"
                />
              </li>
            </ul>
          </section>
        </template>
      </div>
    </div>
  </section>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import type { Route } from "vue-router";
import ConsultationStatusActions from "@/components/ConsultationStatusActions.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import {
  consultationService,
  type Consultation,
  type ConsultationStatus,
} from "@/services/consultationService";
import { getErrorMessage } from "@/services/api";
import {
  groupByDay,
  parseStatusParam,
  parseWeekParam,
  serializeStatus,
  weekLabel,
  weekRange,
  type AgendaDay,
} from "@/utils/agenda";
import { STATUS_META, STATUS_ORDER } from "@/utils/consultationStatus";
import {
  addDays,
  formatDayHeading,
  startOfWeek,
  timePart,
  timeRange,
  todayInSaoPaulo,
} from "@/utils/datetime";
import { debounce } from "@/utils/debounce";

export const PROFESSIONAL_DEBOUNCE = 400;

type Query = Route["query"];

/**
 * Weekly agenda (Mon–Sun, São Paulo). Week and filters live in the query
 * string (?semana=YYYY-MM-DD&status=a,b&profissional=texto) so the view can
 * be linked and the back button works.
 */
export default defineComponent({
  name: "AgendaView",
  components: { ConsultationStatusActions, StatusBadge },
  data() {
    return {
      items: [] as Consultation[],
      loading: false,
      loaded: false,
      error: "",
      announcement: "",
      professional: "",
      today: todayInSaoPaulo(),
      requestId: 0,
      statusOptions: STATUS_ORDER.map((value) => ({
        value,
        label: STATUS_META[value].label,
      })),
    };
  },
  computed: {
    week(): string {
      return parseWeekParam(this.$route.query.semana, this.today);
    },
    currentWeek(): string {
      return startOfWeek(this.today);
    },
    isCurrentWeek(): boolean {
      return this.week === this.currentWeek;
    },
    prevWeek(): string {
      return addDays(this.week, -7);
    },
    nextWeek(): string {
      return addDays(this.week, 7);
    },
    weekLabel(): string {
      return weekLabel(this.week);
    },
    statuses(): ConsultationStatus[] {
      return parseStatusParam(this.$route.query.status);
    },
    professionalQuery(): string {
      const value = this.$route.query.profissional;
      return typeof value === "string" ? value : "";
    },
    days(): AgendaDay[] {
      return groupByDay(this.items, this.week, this.today);
    },
    /** Request key: refetch only when the week or a filter changes. */
    requestKey(): string {
      return [this.week, this.statuses.join(","), this.professionalQuery].join(
        "|"
      );
    },
  },
  watch: {
    requestKey: { immediate: true, handler: "fetch" },
    "$route.query.semana": "canonicalizeWeek",
    professionalQuery: {
      immediate: true,
      handler(value: string) {
        if (value.trim() !== this.professional.trim())
          this.professional = value;
      },
    },
  },
  created() {
    this.applyProfessional = debounce(() => {
      this.updateQuery({ profissional: this.professional.trim() || undefined });
    }, PROFESSIONAL_DEBOUNCE);
    this.canonicalizeWeek();
  },
  beforeDestroy() {
    this.applyProfessional.cancel?.();
  },
  methods: {
    // replaced with a debounced version in created()
    applyProfessional: (() => undefined) as (() => void) & {
      cancel?: () => void;
    },
    dayHeading: formatDayHeading,
    countLabel(n: number): string {
      return n === 1 ? "1 atendimento" : `${n} atendimentos`;
    },
    rangeOf(item: Consultation): string {
      return timeRange(item.scheduled_at, item.duration_minutes);
    },
    startOf(item: Consultation): string {
      return timePart(item.scheduled_at);
    },
    patientIdOf(item: Consultation): string {
      const id = item.patient?.id ?? item.patient_id;
      return id ? String(id) : "";
    },
    patientNameOf(item: Consultation): string {
      return item.patient?.name ?? "Paciente";
    },
    /** ?semana with any day (or an invalid value) -> that week's Monday. */
    canonicalizeWeek(): void {
      const raw = this.$route.query.semana;
      if (raw === undefined) return;
      if (raw !== this.week) {
        this.updateQuery({
          semana: this.week === this.currentWeek ? undefined : this.week,
        });
      }
    },
    updateQuery(changes: Record<string, string | undefined>, push = false) {
      const query: Query = { ...this.$route.query };
      Object.entries(changes).forEach(([key, value]) => {
        if (value === undefined || value === "") delete query[key];
        else query[key] = value;
      });
      const location = { query };
      const nav = push
        ? this.$router.push(location)
        : this.$router.replace(location);
      nav.catch(() => undefined);
    },
    goToWeek(monday: string): void {
      if (monday === this.week) {
        this.announcement = `Semana de ${this.weekLabel} já exibida.`;
        return;
      }
      this.updateQuery(
        { semana: monday === this.currentWeek ? undefined : monday },
        true
      );
    },
    toggleStatus(status: ConsultationStatus): void {
      const next = this.statuses.includes(status)
        ? this.statuses.filter((s) => s !== status)
        : [...this.statuses, status];
      this.updateQuery({ status: serializeStatus(next) || undefined });
    },
    onProfessionalInput(): void {
      this.applyProfessional();
    },
    onChanged(updated: Consultation): void {
      this.items = this.items.map((item) =>
        item.id === updated.id ? { ...item, ...updated } : item
      );
    },
    /** True when a status filter is active and the status is not in it. */
    isFilteredOut(item: Consultation): boolean {
      return this.statuses.length > 0 && !this.statuses.includes(item.status);
    },
    /**
     * After a status change completes: a consultation whose new status no
     * longer matches the status filter leaves the list (removed only now, so
     * its confirmation modal has closed). Focus moves to the next item of the
     * day, else the previous one, else the day heading; the change is announced.
     */
    onSettled(changed: Consultation): void {
      const item = this.items.find((i) => i.id === changed.id);
      if (!item || !this.isFilteredOut(item)) return;
      const day = this.days.find((d) => d.items.some((i) => i.id === item.id));
      const siblings = day ? day.items : [];
      const index = siblings.findIndex((i) => i.id === item.id);
      const neighbour = siblings[index + 1] ?? siblings[index - 1];
      const name = this.patientNameOf(item);
      const start = this.startOf(item);
      this.items = this.items.filter((i) => i.id !== item.id);
      this.announcement = `Atendimento de ${name} às ${start} saiu da lista: o novo status não corresponde ao filtro.`;
      this.$nextTick(() => {
        const selector = neighbour
          ? `#agenda-item-${neighbour.id}`
          : day
          ? `#agenda-day-${day.date}`
          : "#main-content h1";
        const el = document.querySelector<HTMLElement>(selector);
        if (!el) return;
        if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "-1");
        el.focus({ preventScroll: true });
      });
    },
    async fetch(): Promise<void> {
      const id = ++this.requestId;
      this.loading = true;
      this.error = "";
      this.today = todayInSaoPaulo();
      try {
        const items = await consultationService.agenda({
          ...weekRange(this.week),
          status: this.statuses,
          professional: this.professionalQuery,
        });
        if (id !== this.requestId) return;
        this.items = items;
        this.loaded = true;
        this.announcement = `Semana de ${this.weekLabel}: ${
          items.length === 0
            ? "nenhum atendimento"
            : this.countLabel(items.length)
        }.`;
      } catch (e) {
        if (id !== this.requestId) return;
        this.error = getErrorMessage(e, "Tente novamente em instantes.");
      } finally {
        if (id === this.requestId) this.loading = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.week-nav {
  display: flex;
  gap: 8px;
}

.week-nav__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  padding-right: 0;
  padding-left: 0;
}

.agenda-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 16px 24px;
  align-items: flex-end;
  justify-content: space-between;
  padding-bottom: 20px;
  border-bottom: 2px solid $border-light;
}

.agenda-week {
  display: inline-flex;
  align-items: center;
  margin: 0;
  color: $navy;
  font-size: 18px;
  font-weight: 800;
}

.agenda-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-end;
}

.agenda-filters__label {
  display: block;
  width: auto;
  margin-bottom: 6px;
  color: $navy;
  font-size: 14px;
  font-weight: 600;
}

.status-filter {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.status-filter__options {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.status-chip {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  height: 40px;
  margin: 0;
  padding: 0 12px;
  color: $text;
  font-size: 14px;
  font-weight: 600;
  background: #fff;
  border: 1px solid $border;
  border-radius: 999px;
  cursor: pointer;

  &.is-checked {
    color: $navy;
    background: rgba($blue, 0.08);
    border-color: $blue;
  }

  &:focus-within {
    outline: 2px solid $blue;
    outline-offset: 2px;
  }
}

.status-chip__input {
  width: 16px;
  height: 16px;
  margin: 0;
  accent-color: $blue;
}

.professional-filter__field {
  position: relative;
  width: 240px;

  ::v-deep .form-control {
    padding-left: 38px;
  }
}

.professional-filter__icon {
  position: absolute;
  top: 12px;
  left: 13px;
  color: $text-muted;
}

.agenda-days {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-top: 16px;
}

.agenda-day {
  padding: 12px 16px;
  border-left: 3px solid transparent;
  border-radius: $border-radius;

  &.is-today {
    background: rgba($blue, 0.05);
    border-left-color: $blue;
  }

  &.is-empty {
    padding-top: 8px;
    padding-bottom: 8px;
  }
}

.agenda-day__title {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 10px;
  align-items: center;
  margin: 0 0 10px;
  color: $navy;
  font-size: 16px;
  font-weight: 800;

  .is-empty & {
    margin: 0;
    color: $text-muted;
    font-size: 14px;
    font-weight: 700;
  }
}

.today-tag {
  padding: 1px 8px;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  background: $blue;
  border-radius: 999px;
}

.agenda-day__count {
  color: $text-muted;
  font-size: 13px;
  font-weight: 600;
}

.agenda-day__empty {
  margin: 2px 0 0;
  color: $text-muted;
  font-size: 13px;
}

.agenda-items {
  margin: 0;
  padding: 0;
  list-style: none;
}

.agenda-item {
  display: grid;
  grid-template-columns: 120px minmax(0, 1fr) 150px auto;
  gap: 8px 16px;
  align-items: center;
  padding: 12px 14px;
  background: #fff;
  border: 1px solid $border-light;
  border-radius: $border-radius;

  & + & {
    margin-top: 8px;
  }

  &:focus {
    outline: 2px solid $blue;
    outline-offset: 2px;
  }
}

.agenda-item__time {
  display: inline-flex;
  align-items: center;
  color: $navy;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.agenda-item__main {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.agenda-item__patient {
  overflow-wrap: anywhere;
  font-weight: 700;
}

.agenda-item__professional {
  color: $text-muted;
  font-size: 14px;
}

.agenda-item__status {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
}

.agenda-item__open {
  font-size: 13px;
  font-weight: 700;
}

.agenda-item__actions {
  justify-self: end;
}

@media (max-width: 1199.98px) {
  .agenda-item {
    grid-template-columns: 110px minmax(0, 1fr) 150px;
  }

  .agenda-item__actions {
    grid-column: 1 / -1;
    justify-self: start;
  }
}

@media (max-width: 767.98px) {
  .agenda-filters,
  .professional-filter,
  .professional-filter__field {
    width: 100%;
  }
}

@media (max-width: 575.98px) {
  .page-header {
    align-items: flex-start;
  }

  .agenda-day {
    padding: 10px 8px;
  }

  .agenda-item {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
  }

  .agenda-item__status {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 6px 12px;
    align-items: center;
  }
}
</style>
