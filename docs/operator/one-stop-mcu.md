# One Stop MCU

One Stop MCU is an additive Operator episode for a participant who has completed the existing identity verification, consent, and check-in flow. The Operator worklist only shows checked-in admissions at the active authorized site and assigned shift.

Saving an MCU examination creates one immutable record linked to the participant, booking, shift, checked-in queue admission, site, and examining Operator. It does not change the admission or complete Basic Examination, Radiography, or Doctor work. The existing identity, registration, authorization, and site-assignment services remain the source of those decisions.

The examination stores blood pressure, weight, one height value in centimetres, temperature, GCU results and fasting context, three required positive PEF attempts, their maximum, and notes. The save boundary rejects a missing or invalid attempt; the highest value is never an average. BMI is calculated from the persisted weight and height. Microtoise is shown only as height-measurement method context; it is not a second numeric result. No clinical thresholds or interpretations are added.

The protected PDF route loads the saved MCU record and canonical participant identity. It displays NIK by decrypting the established `members.encrypted_nik` value through `ProtectedIdentifierService`; MRN remains the internal member/imaging identifier and is not copied into MCU storage. The report uses the Rumah Skrining CV Prestige identity and contact block and the informational dr. Noor Istichawari (dr. Nunung) consultation footer. It includes the screening disclaimer and manual signature areas, and supports browser print/view and attachment download. The existing mPDF dependency is reused; no WhatsApp integration or outbound action is performed.
