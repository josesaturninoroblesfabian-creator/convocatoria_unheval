export const defaultScreeningRules = () => ({
    enabled: true,
    minimum_degree: "maestro",
    minimum_experience_months: 60,
    experience_type: "toda",
    count_from_bachelor: true,
    minimum_training_hours: 0,
    degree_points: { titulo: 2, maestro: 6, doctor: 8 },
    experience_points_per_year: 1,
    experience_cap: 10,
    training_hours_per_point: 40,
    training_cap: 5,
    fields: [],
});
