import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = [
    "row",
    "workingCheckbox",
    "morningFields",
    "afternoonFields",
    "addAfternoonButton",
    "removeButton",
  ];

  connect() {
    this.rowTargets.forEach((row) => this.syncRow(row));
  }

  toggleDay(event) {
  const row = event.currentTarget.closest('[data-secretary-availability-target="row"]');
  const morningInputs = row.querySelectorAll('[data-secretary-availability-target="morningFields"] input');

  if (event.currentTarget.checked) {
    if (morningInputs[0] && !morningInputs[0].value) {
      morningInputs[0].value = "08:00";
    }

    if (morningInputs[1] && !morningInputs[1].value) {
      morningInputs[1].value = "12:00";
    }
  }

  this.syncRow(row);
}

  addAfternoon(event) {
    const row = event.currentTarget.closest('[data-secretary-availability-target="row"]');

    const checkbox = row.querySelector(
      '[data-secretary-availability-target="workingCheckbox"]',
    );
    const morningInputs = row.querySelectorAll(
      '[data-secretary-availability-target="morningFields"] input',
    );
    const afternoonInputs = row.querySelectorAll(
      '[data-secretary-availability-target="afternoonFields"] input',
    );

    const isWorking = checkbox?.checked === true;
    const hasAfternoonVisible = row.dataset.afternoonVisible === "1";

    if (checkbox) {
      checkbox.checked = true;
    }

    // Si le jour était fermé : 1er clic = matin uniquement
    if (!isWorking) {
      row.dataset.afternoonVisible = "0";

      if (morningInputs[0]) morningInputs[0].value = "08:00";
      if (morningInputs[1]) morningInputs[1].value = "12:00";

      afternoonInputs.forEach((input) => {
        input.value = "";
      });

      this.syncRow(row);
      return;
    }

    // Si le matin existe déjà : 2e clic = après-midi
    if (!hasAfternoonVisible) {
      row.dataset.afternoonVisible = "1";

      if (afternoonInputs[0]) afternoonInputs[0].value = "14:00";
      if (afternoonInputs[1]) afternoonInputs[1].value = "18:00";
    }

    this.syncRow(row);
  }

  removeSlot(event) {
    const row = event.currentTarget.closest('[data-secretary-availability-target="row"]');
    const checkbox = row.querySelector(
      '[data-secretary-availability-target="workingCheckbox"]',
    );
    const morningInputs = row.querySelectorAll(
      '[data-secretary-availability-target="morningFields"] input',
    );
    const afternoonInputs = row.querySelectorAll(
      '[data-secretary-availability-target="afternoonFields"] input',
    );

    const hasAfternoon =
      afternoonInputs[0]?.value !== "" && afternoonInputs[1]?.value !== "";
    const hasMorning =
      morningInputs[0]?.value !== "" && morningInputs[1]?.value !== "";

    if (hasAfternoon) {
      row.dataset.afternoonVisible = "0";
      afternoonInputs.forEach((input) => (input.value = ""));
    } else if (hasMorning) {
      morningInputs.forEach((input) => (input.value = ""));
      if (checkbox) checkbox.checked = false;
    }

    this.syncRow(row);
  }

  syncRow(row) {
    const checkbox = row.querySelector(
      '[data-secretary-availability-target="workingCheckbox"]',
    );
    const morning = row.querySelector(
      '[data-secretary-availability-target="morningFields"]',
    );
    const afternoon = row.querySelector(
      '[data-secretary-availability-target="afternoonFields"]',
    );
    const addButton = row.querySelector(
      '[data-secretary-availability-target="addAfternoonButton"]',
    );
    const removeButton = row.querySelector(
      '[data-secretary-availability-target="removeButton"]',
    );

    const morningInputs = row.querySelectorAll(
      '[data-secretary-availability-target="morningFields"] input',
    );
    const afternoonInputs = row.querySelectorAll(
      '[data-secretary-availability-target="afternoonFields"] input',
    );

    const isWorking = checkbox?.checked === true;

    const hasMorning =
      morningInputs[0]?.value !== "" && morningInputs[1]?.value !== "";
    const hasAfternoon =
      afternoonInputs[0]?.value !== "" && afternoonInputs[1]?.value !== "";
    const hasAnySlot = hasMorning || hasAfternoon;

    const hasBothSlots = isWorking && hasMorning && hasAfternoon;

    row.classList.toggle("table-light", !isWorking);

    morning?.classList.toggle("d-none", !isWorking || !hasMorning);
    afternoon?.classList.toggle("d-none", !isWorking || !hasAfternoon);

    if (addButton) {
      const disableAdd = hasMorning && hasAfternoon;

      addButton.disabled = disableAdd;

      addButton.classList.remove(
        "btn-outline-primary",
        "btn-outline-secondary",
      );

      addButton.classList.add(
        disableAdd ? "btn-outline-secondary" : "btn-outline-primary",
      );
    }
    console.log("ADD", {
      isWorking,
      hasMorning,
      hasAfternoon,
      hasBothSlots,
      disabled: addButton?.disabled,
    });

    if (removeButton) {
      removeButton.disabled = !hasAnySlot;

      removeButton.classList.remove(
        "btn-outline-danger",
        "btn-danger",
        "btn-warning",
        "btn-secondary",
        "btn-outline-secondary",
        "disabled",
      );

      removeButton.classList.add(
        !hasAnySlot ? "btn-outline-secondary" : "btn-outline-danger",
      );
    }
  }
}
