/**
 * @public
 */
var AuthenticatorType = /*#__PURE__*/function (AuthenticatorType) {
  AuthenticatorType["Password"] = "password";
  AuthenticatorType["OOBOTPEmail"] = "oob_otp_email";
  AuthenticatorType["OOBOTPSMS"] = "oob_otp_sms";
  AuthenticatorType["TOTP"] = "totp";
  AuthenticatorType["Passkey"] = "passkey";
  AuthenticatorType["Unknown"] = "unknown";
  return AuthenticatorType;
}({});

/**
 * @public
 */
var AuthenticatorKind = /*#__PURE__*/function (AuthenticatorKind) {
  AuthenticatorKind["Primary"] = "primary";
  AuthenticatorKind["Secondary"] = "secondary";
  AuthenticatorKind["Unknown"] = "unknown";
  return AuthenticatorKind;
}({});

/**
 * @public
 */
var IdentityType = /*#__PURE__*/function (IdentityType) {
  IdentityType["LoginID"] = "login_id";
  IdentityType["OAuth"] = "oauth";
  IdentityType["Anonymous"] = "anonymous";
  IdentityType["Biometric"] = "biometric";
  IdentityType["Passkey"] = "passkey";
  IdentityType["SIWE"] = "siwe";
  IdentityType["LDAP"] = "ldap";
  IdentityType["Unknown"] = "unknown";
  return IdentityType;
}({});

/**
 * @public
 */
var LoginIDType = /*#__PURE__*/function (LoginIDType) {
  LoginIDType["Email"] = "email";
  LoginIDType["Phone"] = "phone";
  LoginIDType["Username"] = "username";
  LoginIDType["Unknown"] = "unknown";
  return LoginIDType;
}({});

/**
 * @public
 */

/**
 * @public
 */

/**
 * UserInfo is the result of fetchUserInfo.
 * It contains `sub` which is the User ID,
 * as well as OIDC standard claims like `email`,
 * see https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims.
 *
 * In addition to these standard claims, it may include custom claims
 * defined by Authgear to support additional functionality like `isVerified`.
 *
 * @public
 */

/**
 * ColorScheme represents the color scheme supported by Authgear.
 * A colorscheme is either light or dark. Authgear supports both by default.
 *
 * @public
 */
var ColorScheme = /*#__PURE__*/function (ColorScheme) {
  /**
   * Force to use the light color scheme in the AuthUI when the project config is "Auto".
   */
  ColorScheme["Light"] = "light";
  /**
   * Force to use the dark color scheme in the AuthUI when the project config is "Auto".
   */
  ColorScheme["Dark"] = "dark";
  return ColorScheme;
}({});

/**
 * Prompt parameter options.
 *
 * @public
 */
var PromptOption = /*#__PURE__*/function (PromptOption) {
  /**
   * The `none` prompt is used to sliently authenticate the user without prompting for any action.
   * This prompt bypasses the need for `login` and `consent` prompts
   * only when the user has previously given consent to the application and has an active session.
   */
  PromptOption["None"] = "none";
  /**
   * The `login` prompt requires the user to log in to the authentication provider which forces the user to re-authenticate.
   */
  PromptOption["Login"] = "login";
  /**
   * The `consent` prompt asks the user to consent to the scopes.
   *
   * @internal
   */
  PromptOption["Consent"] = "consent";
  /**
   * The select_account prompt present a "Continue" screen to for the user to choose
   * to continue with the session in the cookies or login to another account.
   *
   * @internal
   */
  PromptOption["SelectAccount"] = "select_account";
  return PromptOption;
}({});

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */
function _decodeAuthenticators(r) {
  if (!Array.isArray(r)) {
    return undefined;
  }
  return r.map(function (a) {
    return {
      createdAt: new Date(a["created_at"]),
      updatedAt: new Date(a["updated_at"]),
      type: parseAuthenticatorType(a["type"]),
      kind: parseAuthenticatorKind(a["kind"])
    };
  });
}

/**
 * @internal
 */
function parseLoginIDType(value) {
  switch (value) {
    case "email":
      return LoginIDType.Email;
    case "phone":
      return LoginIDType.Phone;
    case "username":
      return LoginIDType.Username;
    default:
      return LoginIDType.Unknown;
  }
}

/**
 * @internal
 */
function parseIdentityType(value) {
  switch (value) {
    case "login_id":
      return IdentityType.LoginID;
    case "oauth":
      return IdentityType.OAuth;
    case "anonymous":
      return IdentityType.Anonymous;
    case "biometric":
      return IdentityType.Biometric;
    case "passkey":
      return IdentityType.Passkey;
    case "siwe":
      return IdentityType.SIWE;
    case "ldap":
      return IdentityType.LDAP;
    default:
      return IdentityType.Unknown;
  }
}

/**
 * @internal
 */
function _decodeIdentities(r) {
  if (!Array.isArray(r)) {
    return undefined;
  }
  return r.map(function (i) {
    var identity = {
      type: parseIdentityType(i["type"]),
      createdAt: new Date(i["created_at"]),
      updatedAt: new Date(i["updated_at"])
    };
    if (identity.type === IdentityType.LoginID) {
      identity.loginIDKey = i["login_id_key"];
      identity.loginIDType = i["login_id_type"] != null ? parseLoginIDType(i["login_id_type"]) : undefined;
    }
    if (identity.type === IdentityType.OAuth) {
      identity.oauthProviderType = i["oauth_provider_type"];
      identity.oauthProviderAlias = i["oauth_provider_alias"];
    }
    return identity;
  });
}

/**
 * @internal
 */
function parseAuthenticatorType(value) {
  switch (value) {
    case "password":
      return AuthenticatorType.Password;
    case "oob_otp_email":
      return AuthenticatorType.OOBOTPEmail;
    case "oob_otp_sms":
      return AuthenticatorType.OOBOTPSMS;
    case "totp":
      return AuthenticatorType.TOTP;
    case "passkey":
      return AuthenticatorType.Passkey;
    default:
      return AuthenticatorType.Unknown;
  }
}

/**
 * @internal
 */
function parseAuthenticatorKind(value) {
  switch (value) {
    case "primary":
      return AuthenticatorKind.Primary;
    case "secondary":
      return AuthenticatorKind.Secondary;
    default:
      return AuthenticatorKind.Unknown;
  }
}

/**
 * @internal
 */
function _decodeUserInfo(r) {
  var _r$custom_attributes, _r$httpsAuthgearC, _r$httpsAuthgearC2, _r$httpsAuthgearC3, _r$httpsAuthgearC4, _r$address, _r$address2, _r$address3, _r$address4, _r$address5, _r$address6;
  var raw = r;
  var customAttributes = (_r$custom_attributes = r["custom_attributes"]) != null ? _r$custom_attributes : {};
  return {
    sub: r["sub"],
    isVerified: (_r$httpsAuthgearC = r["https://authgear.com/claims/user/is_verified"]) != null ? _r$httpsAuthgearC : false,
    isAnonymous: (_r$httpsAuthgearC2 = r["https://authgear.com/claims/user/is_anonymous"]) != null ? _r$httpsAuthgearC2 : false,
    canReauthenticate: (_r$httpsAuthgearC3 = r["https://authgear.com/claims/user/can_reauthenticate"]) != null ? _r$httpsAuthgearC3 : false,
    recoveryCodeEnabled: (_r$httpsAuthgearC4 = r["https://authgear.com/claims/user/recovery_code_enabled"]) != null ? _r$httpsAuthgearC4 : false,
    roles: r["https://authgear.com/claims/user/roles"],
    authenticators: _decodeAuthenticators(r["https://authgear.com/claims/user/authenticators"]),
    identities: _decodeIdentities(r["https://authgear.com/claims/user/identities"]),
    raw: raw,
    customAttributes: customAttributes,
    email: r["email"],
    emailVerified: r["email_verified"],
    phoneNumber: r["phone_number"],
    phoneNumberVerified: r["phone_number_verified"],
    preferredUsername: r["preferred_username"],
    familyName: r["family_name"],
    givenName: r["given_name"],
    middleName: r["middle_name"],
    name: r["name"],
    nickname: r["nickname"],
    picture: r["picture"],
    profile: r["profile"],
    website: r["website"],
    gender: r["gender"],
    birthdate: r["birthdate"],
    zoneinfo: r["zoneinfo"],
    locale: r["locale"],
    address: {
      formatted: (_r$address = r["address"]) == null ? void 0 : _r$address["formatted"],
      streetAddress: (_r$address2 = r["address"]) == null ? void 0 : _r$address2["street_address"],
      locality: (_r$address3 = r["address"]) == null ? void 0 : _r$address3["locality"],
      region: (_r$address4 = r["address"]) == null ? void 0 : _r$address4["region"],
      postalCode: (_r$address5 = r["address"]) == null ? void 0 : _r$address5["postal_code"],
      country: (_r$address6 = r["address"]) == null ? void 0 : _r$address6["country"]
    }
  };
}

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * TokenStorage is an interface controlling when refresh tokens are stored.
 * Normally you do not need to implement this interface.
 * You can use one of those implementations provided by the SDK.
 *
 * @public
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * Options for the constructor of a Container.
 *
 * @public
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * The session state.
 *
 * An freshly constructed instance has the session state "UNKNOWN";
 *
 * After a call to configure, the session state would become "AUTHENTICATED" if a previous session was found,
 * or "NO_SESSION" if such session was not found.
 *
 * Please refer to {@link SessionStateChangeReason} for more information.
 *
 * @public
 */
var SessionState = /*#__PURE__*/function (SessionState) {
  SessionState["Unknown"] = "UNKNOWN";
  SessionState["NoSession"] = "NO_SESSION";
  SessionState["Authenticated"] = "AUTHENTICATED";
  return SessionState;
}({});

/**
 * The reason why SessionState is changed.
 *
 * These reasons can be thought of as the transition of a SessionState, which is described as follows:
 *
 * ```
 *                                                      LOGOUT / INVALID / CLEAR
 *                                           +----------------------------------------------+
 *                                           v                                              |
 *    State: UNKNOWN ----- NO_TOKEN ----> State: NO_SESSION ---- AUTHENTICATED -----> State: AUTHENTICATED
 *      |                                                                                ^
 *      +--------------------------------------------------------------------------------+
 *                                         FOUND_TOKEN
 * ```
 * @public
 */
var SessionStateChangeReason = /*#__PURE__*/function (SessionStateChangeReason) {
  SessionStateChangeReason["NoToken"] = "NO_TOKEN";
  SessionStateChangeReason["FoundToken"] = "FOUND_TOKEN";
  SessionStateChangeReason["Authenticated"] = "AUTHENTICATED";
  SessionStateChangeReason["Logout"] = "LOGOUT";
  SessionStateChangeReason["Invalid"] = "INVALID";
  SessionStateChangeReason["Clear"] = "CLEAR";
  return SessionStateChangeReason;
}({});

/**
 * The path of the page in Authgear.
 *
 * @public
 */
var Page = /*#__PURE__*/function (Page) {
  /**
   * The path of the settings page in Authgear.
   */
  Page["Settings"] = "/settings";
  /**
   * The path of the indenties page in Authgear.
   */
  Page["Identities"] = "/settings/identities";
  return Page;
}({});

/**
 * The actions that can be performed in Authgear settings page.
 *
 * @public
 */
var SettingsAction = /*#__PURE__*/function (SettingsAction) {
  /**
   * Change password in Authgear settings page.
   */
  SettingsAction["ChangePassword"] = "change_password";
  /**
   * Delete account in Authgear settings page.
   */
  SettingsAction["DeleteAccount"] = "delete_account";
  /**
   * Add email in Authgear settings page.
   */
  SettingsAction["AddEmail"] = "add_email";
  /**
   * Add phone in Authgear settings page.
   */
  SettingsAction["AddPhone"] = "add_phone";
  /**
   * Add username in Authgear settings page.
   */
  SettingsAction["AddUsername"] = "add_username";
  /**
   * Change email in Authgear settings page.
   */
  SettingsAction["ChangeEmail"] = "change_email";
  /**
   * Change phone in Authgear settings page.
   */
  SettingsAction["ChangePhone"] = "change_phone";
  /**
   * Change username in Authgear settings page.
   */
  SettingsAction["ChangeUsername"] = "change_username";
  /**
   * Link an OAuth provider in Authgear settings page.
   */
  SettingsAction["LinkOAuth"] = "link_oauth";
  /**
   * Unlink an OAuth provider in Authgear settings page.
   */
  SettingsAction["UnlinkOAuth"] = "unlink_oauth";
  return SettingsAction;
}({});

/**
 * @internal
 */

function _typeof$1(o) {
  "@babel/helpers - typeof";

  return _typeof$1 = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
    return typeof o;
  } : function (o) {
    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
  }, _typeof$1(o);
}

function toPrimitive$2(t, r) {
  if ("object" != _typeof$1(t) || !t) return t;
  var e = t[Symbol.toPrimitive];
  if (void 0 !== e) {
    var i = e.call(t, r);
    if ("object" != _typeof$1(i)) return i;
    throw new TypeError("@@toPrimitive must return a primitive value.");
  }
  return (String )(t);
}

function toPropertyKey$3(t) {
  var i = toPrimitive$2(t, "string");
  return "symbol" == _typeof$1(i) ? i : i + "";
}

function _defineProperties(e, r) {
  for (var t = 0; t < r.length; t++) {
    var o = r[t];
    o.enumerable = o.enumerable || false, o.configurable = true, "value" in o && (o.writable = true), Object.defineProperty(e, toPropertyKey$3(o.key), o);
  }
}
function _createClass(e, r, t) {
  return r && _defineProperties(e.prototype, r), Object.defineProperty(e, "prototype", {
    writable: false
  }), e;
}

function _assertThisInitialized(e) {
  if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
  return e;
}

function _possibleConstructorReturn(t, e) {
  if (e && ("object" == _typeof$1(e) || "function" == typeof e)) return e;
  if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined");
  return _assertThisInitialized(t);
}

function _getPrototypeOf(t) {
  return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) {
    return t.__proto__ || Object.getPrototypeOf(t);
  }, _getPrototypeOf(t);
}

function _setPrototypeOf(t, e) {
  return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) {
    return t.__proto__ = e, t;
  }, _setPrototypeOf(t, e);
}

function _inherits(t, e) {
  if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
  t.prototype = Object.create(e && e.prototype, {
    constructor: {
      value: t,
      writable: true,
      configurable: true
    }
  }), Object.defineProperty(t, "prototype", {
    writable: false
  }), e && _setPrototypeOf(t, e);
}

function _isNativeFunction(t) {
  try {
    return -1 !== Function.toString.call(t).indexOf("[native code]");
  } catch (n) {
    return "function" == typeof t;
  }
}

function _isNativeReflectConstruct$1() {
  try {
    var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {}));
  } catch (t) {}
  return (_isNativeReflectConstruct$1 = function _isNativeReflectConstruct() {
    return !!t;
  })();
}

function _construct(t, e, r) {
  if (_isNativeReflectConstruct$1()) return Reflect.construct.apply(null, arguments);
  var o = [null];
  o.push.apply(o, e);
  var p = new (t.bind.apply(t, o))();
  return r && _setPrototypeOf(p, r.prototype), p;
}

function _wrapNativeSuper(t) {
  var r = "function" == typeof Map ? new Map() : void 0;
  return _wrapNativeSuper = function _wrapNativeSuper(t) {
    if (null === t || !_isNativeFunction(t)) return t;
    if ("function" != typeof t) throw new TypeError("Super expression must either be null or a function");
    if (void 0 !== r) {
      if (r.has(t)) return r.get(t);
      r.set(t, Wrapper);
    }
    function Wrapper() {
      return _construct(t, arguments, _getPrototypeOf(this).constructor);
    }
    return Wrapper.prototype = Object.create(t.prototype, {
      constructor: {
        value: Wrapper,
        enumerable: false,
        writable: true,
        configurable: true
      }
    }), _setPrototypeOf(Wrapper, t);
  }, _wrapNativeSuper(t);
}

function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
/**
 * AuthgearError is the root class of error produced by the SDK.
 *
 * @public
 */
var AuthgearError = /*#__PURE__*/function (_Error) {
  function AuthgearError() {
    return _callSuper(this, AuthgearError, arguments);
  }
  _inherits(AuthgearError, _Error);
  return _createClass(AuthgearError);
}(/*#__PURE__*/_wrapNativeSuper(Error));

/**
 * ErrorName contains all possible name in {@link ServerError}
 *
 * @public
 */
var ErrorName = /*#__PURE__*/function (ErrorName) {
  /**
   * Indicates that the server does not understand the request (i.e. syntactic error).
   * Status code: 400
   */
  ErrorName["BadRequest"] = "BadRequest";
  /**
   * Indicates that the server understands the request, but refuse to process it (i.e. semantic error).
   * Status code: 400
   */
  ErrorName["Invalid"] = "Invalid";
  /**
   * Indicates that the client does not have valid credentials (i.e. authentication error).
   * Status code: 401
   */
  ErrorName["Unauthorized"] = "Unauthorized";
  /**
   * Indicates that the client's credentials are not allowed for the request (i.e. authorization error).
   * Status code: 403
   */
  ErrorName["Forbidden"] = "Forbidden";
  /**
   * Indicates that the server cannot find the requested resource.
   * Status code: 404
   */
  ErrorName["NotFound"] = "NotFound";
  /**
   * Indicates that the resource is already exists on the server.
   * Status code: 409
   */
  ErrorName["AlreadyExists"] = "AlreadyExists";
  /**
   * Indicates that the client has sent too many requests in a given amount of time.
   * Status code: 429
   */
  ErrorName["TooManyRequest"] = "TooManyRequest";
  /**
   * Indicates that the server encountered an unexpected condition and unable to process the request.
   * Status code: 500
   */
  ErrorName["InternalError"] = "InternalError";
  /**
   * Indicates that the server is not ready to handle the request.
   * Status code: 503
   */
  ErrorName["ServiceUnavailable"] = "ServiceUnavailable";
  return ErrorName;
}({});

/**
 * CancelError means cancel.
 * If you catch an error and it is instanceof CancelError,
 * then the operation was cancelled.
 *
 * @public
 */
var CancelError = /*#__PURE__*/function (_AuthgearError) {
  function CancelError() {
    return _callSuper(this, CancelError, arguments);
  }
  _inherits(CancelError, _AuthgearError);
  return _createClass(CancelError);
}(AuthgearError);

/**
 * ServerError represents error received from the server.
 *
 * @public
 */
var ServerError = /*#__PURE__*/function (_AuthgearError2) {
  /**
   * Error name.
   *
   * @remarks
   * See {@link ErrorName} for possible values.
   * New error names may be added in future.
   */

  /**
   * Error message.
   *
   * @remarks
   * Error messages are provided for convenience, and not stable APIs;
   * Consumers should use {@link ServerError.name} or
   * {@link ServerError.reason} to distinguish between different errors.
   */

  /**
   * Error reason.
   */

  /**
   * Additional error information.
   */

  function ServerError(message, name, reason, info) {
    var _this;
    _this = _callSuper(this, ServerError, [message]);
    _this.name = name;
    _this.reason = reason;
    _this.info = info;
    return _this;
  }
  _inherits(ServerError, _AuthgearError2);
  return _createClass(ServerError);
}(AuthgearError);

/**
 * OAuthError represents the oauth error response.
 * https://tools.ietf.org/html/rfc6749#section-4.1.2.1
 *
 * @public
 */
var OAuthError = /*#__PURE__*/function (_AuthgearError3) {
  function OAuthError(_ref) {
    var _this2;
    var state = _ref.state,
      error = _ref.error,
      error_description = _ref.error_description,
      error_uri = _ref.error_uri;
    _this2 = _callSuper(this, OAuthError, [error + (error_description != null ? ": " + error_description : "")]);
    _this2.state = state;
    _this2.error = error;
    _this2.error_description = error_description;
    _this2.error_uri = error_uri;
    return _this2;
  }
  _inherits(OAuthError, _AuthgearError3);
  return _createClass(OAuthError);
}(AuthgearError);

/**
 * @internal
 */
// eslint-disable-next-line complexity
function _decodeError(err) {
  // Construct ServerError if it looks like one.
  if (err != null && !(err instanceof Error) && typeof err.name === "string" && typeof err.reason === "string" && typeof err.message === "string") {
    return new ServerError(err.message, err.name, err.reason, err.info);
  }
  // If it is an Error, just return it.
  if (err instanceof Error) {
    return err;
  }
  // If it has message, construct an Error from the message.
  if (err != null && typeof err.message === "string") {
    return new Error(err.message);
  }
  // If it can be turned into string, use it as message.
  if (err != null && typeof err.toString === "function") {
    return new Error(err.toString());
  }
  // Otherwise cast it to string and use it as message.
  return new Error(String(err));
}

/**
 * PreAuthenticatedURLNotAllowedError is the root class of errors related to pre-authenticated URL.
 *
 * @public
 */
var PreAuthenticatedURLNotAllowedError = /*#__PURE__*/function (_AuthgearError4) {
  function PreAuthenticatedURLNotAllowedError() {
    return _callSuper(this, PreAuthenticatedURLNotAllowedError, arguments);
  }
  _inherits(PreAuthenticatedURLNotAllowedError, _AuthgearError4);
  return _createClass(PreAuthenticatedURLNotAllowedError);
}(AuthgearError);

/**
 * This may happen if the "Pre-authenticated URL" feature was not enabled when the user logged in during this session.
 * Ask the user to log in again to enable this feature.
 *
 * @public
 */
var PreAuthenticatedURLInsufficientScopeError = /*#__PURE__*/function (_PreAuthenticatedURLN) {
  function PreAuthenticatedURLInsufficientScopeError() {
    return _callSuper(this, PreAuthenticatedURLInsufficientScopeError, arguments);
  }
  _inherits(PreAuthenticatedURLInsufficientScopeError, _PreAuthenticatedURLN);
  return _createClass(PreAuthenticatedURLInsufficientScopeError);
}(PreAuthenticatedURLNotAllowedError);

/**
 * The user logged in from an older SDK version that does not support the pre-authenticated URL.
 * Ask the user to log in again to resolve the problem.
 *
 * @public
 */
var PreAuthenticatedURLIDTokenNotFoundError = /*#__PURE__*/function (_PreAuthenticatedURLN2) {
  function PreAuthenticatedURLIDTokenNotFoundError() {
    return _callSuper(this, PreAuthenticatedURLIDTokenNotFoundError, arguments);
  }
  _inherits(PreAuthenticatedURLIDTokenNotFoundError, _PreAuthenticatedURLN2);
  return _createClass(PreAuthenticatedURLIDTokenNotFoundError);
}(PreAuthenticatedURLNotAllowedError);

/**
 * The device secret is not found. This may happen if the "Pre-authenticated URL" feature was not enabled when the user logged in during this session.
 * Ask the user to log in again to enable this feature.
 *
 * @public
 */
var PreAuthenticatedURLDeviceSecretNotFoundError = /*#__PURE__*/function (_PreAuthenticatedURLN3) {
  function PreAuthenticatedURLDeviceSecretNotFoundError() {
    return _callSuper(this, PreAuthenticatedURLDeviceSecretNotFoundError, arguments);
  }
  _inherits(PreAuthenticatedURLDeviceSecretNotFoundError, _PreAuthenticatedURLN3);
  return _createClass(PreAuthenticatedURLDeviceSecretNotFoundError);
}(PreAuthenticatedURLNotAllowedError);

function asyncGeneratorStep(n, t, e, r, o, a, c) {
  try {
    var i = n[a](c),
      u = i.value;
  } catch (n) {
    return void e(n);
  }
  i.done ? t(u) : Promise.resolve(u).then(r, o);
}
function _asyncToGenerator(n) {
  return function () {
    var t = this,
      e = arguments;
    return new Promise(function (r, o) {
      var a = n.apply(t, e);
      function _next(n) {
        asyncGeneratorStep(a, r, o, _next, _throw, "next", n);
      }
      function _throw(n) {
        asyncGeneratorStep(a, r, o, _next, _throw, "throw", n);
      }
      _next(void 0);
    });
  };
}

var commonjsGlobal = typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : typeof self !== 'undefined' ? self : {};

function getDefaultExportFromCjs (x) {
	return x && x.__esModule && Object.prototype.hasOwnProperty.call(x, 'default') ? x['default'] : x;
}

var regeneratorRuntime$1 = {exports: {}};

var OverloadYield = {exports: {}};

(function (module) {
	function _OverloadYield(e, d) {
	  this.v = e, this.k = d;
	}
	module.exports = _OverloadYield, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (OverloadYield));

var OverloadYieldExports = OverloadYield.exports;

var regenerator$1 = {exports: {}};

var regeneratorDefine = {exports: {}};

(function (module) {
	function _regeneratorDefine(e, r, n, t) {
	  var i = Object.defineProperty;
	  try {
	    i({}, "", {});
	  } catch (e) {
	    i = 0;
	  }
	  module.exports = _regeneratorDefine = function regeneratorDefine(e, r, n, t) {
	    function o(r, n) {
	      _regeneratorDefine(e, r, function (e) {
	        return this._invoke(r, n, e);
	      });
	    }
	    r ? i ? i(e, r, {
	      value: n,
	      enumerable: !t,
	      configurable: !t,
	      writable: !t
	    }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2));
	  }, module.exports.__esModule = true, module.exports["default"] = module.exports, _regeneratorDefine(e, r, n, t);
	}
	module.exports = _regeneratorDefine, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorDefine));

var regeneratorDefineExports = regeneratorDefine.exports;

(function (module) {
	var regeneratorDefine = regeneratorDefineExports;
	function _regenerator() {
	  /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */
	  var e,
	    t,
	    r = "function" == typeof Symbol ? Symbol : {},
	    n = r.iterator || "@@iterator",
	    o = r.toStringTag || "@@toStringTag";
	  function i(r, n, o, i) {
	    var c = n && n.prototype instanceof Generator ? n : Generator,
	      u = Object.create(c.prototype);
	    return regeneratorDefine(u, "_invoke", function (r, n, o) {
	      var i,
	        c,
	        u,
	        f = 0,
	        p = o || [],
	        y = false,
	        G = {
	          p: 0,
	          n: 0,
	          v: e,
	          a: d,
	          f: d.bind(e, 4),
	          d: function d(t, r) {
	            return i = t, c = 0, u = e, G.n = r, a;
	          }
	        };
	      function d(r, n) {
	        for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) {
	          var o,
	            i = p[t],
	            d = G.p,
	            l = i[2];
	          r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0));
	        }
	        if (o || r > 1) return a;
	        throw y = true, n;
	      }
	      return function (o, p, l) {
	        if (f > 1) throw TypeError("Generator is already running");
	        for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) {
	          i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u);
	          try {
	            if (f = 2, i) {
	              if (c || (o = "next"), t = i[o]) {
	                if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object");
	                if (!t.done) return t;
	                u = t.value, c < 2 && (c = 0);
	              } else 1 === c && (t = i["return"]) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1);
	              i = e;
	            } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break;
	          } catch (t) {
	            i = e, c = 1, u = t;
	          } finally {
	            f = 1;
	          }
	        }
	        return {
	          value: t,
	          done: y
	        };
	      };
	    }(r, o, i), true), u;
	  }
	  var a = {};
	  function Generator() {}
	  function GeneratorFunction() {}
	  function GeneratorFunctionPrototype() {}
	  t = Object.getPrototypeOf;
	  var c = [][n] ? t(t([][n]())) : (regeneratorDefine(t = {}, n, function () {
	      return this;
	    }), t),
	    u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c);
	  function f(e) {
	    return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, regeneratorDefine(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e;
	  }
	  return GeneratorFunction.prototype = GeneratorFunctionPrototype, regeneratorDefine(u, "constructor", GeneratorFunctionPrototype), regeneratorDefine(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", regeneratorDefine(GeneratorFunctionPrototype, o, "GeneratorFunction"), regeneratorDefine(u), regeneratorDefine(u, o, "Generator"), regeneratorDefine(u, n, function () {
	    return this;
	  }), regeneratorDefine(u, "toString", function () {
	    return "[object Generator]";
	  }), (module.exports = _regenerator = function _regenerator() {
	    return {
	      w: i,
	      m: f
	    };
	  }, module.exports.__esModule = true, module.exports["default"] = module.exports)();
	}
	module.exports = _regenerator, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regenerator$1));

var regeneratorExports = regenerator$1.exports;

var regeneratorAsync = {exports: {}};

var regeneratorAsyncGen = {exports: {}};

var regeneratorAsyncIterator = {exports: {}};

(function (module) {
	var OverloadYield = OverloadYieldExports;
	var regeneratorDefine = regeneratorDefineExports;
	function AsyncIterator(t, e) {
	  function n(r, o, i, f) {
	    try {
	      var c = t[r](o),
	        u = c.value;
	      return u instanceof OverloadYield ? e.resolve(u.v).then(function (t) {
	        n("next", t, i, f);
	      }, function (t) {
	        n("throw", t, i, f);
	      }) : e.resolve(u).then(function (t) {
	        c.value = t, i(c);
	      }, function (t) {
	        return n("throw", t, i, f);
	      });
	    } catch (t) {
	      f(t);
	    }
	  }
	  var r;
	  this.next || (regeneratorDefine(AsyncIterator.prototype), regeneratorDefine(AsyncIterator.prototype, "function" == typeof Symbol && Symbol.asyncIterator || "@asyncIterator", function () {
	    return this;
	  })), regeneratorDefine(this, "_invoke", function (t, o, i) {
	    function f() {
	      return new e(function (e, r) {
	        n(t, i, e, r);
	      });
	    }
	    return r = r ? r.then(f, f) : f();
	  }, true);
	}
	module.exports = AsyncIterator, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorAsyncIterator));

var regeneratorAsyncIteratorExports = regeneratorAsyncIterator.exports;

(function (module) {
	var regenerator = regeneratorExports;
	var regeneratorAsyncIterator = regeneratorAsyncIteratorExports;
	function _regeneratorAsyncGen(r, e, t, o, n) {
	  return new regeneratorAsyncIterator(regenerator().w(r, e, t, o), n || Promise);
	}
	module.exports = _regeneratorAsyncGen, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorAsyncGen));

var regeneratorAsyncGenExports = regeneratorAsyncGen.exports;

(function (module) {
	var regeneratorAsyncGen = regeneratorAsyncGenExports;
	function _regeneratorAsync(n, e, r, t, o) {
	  var a = regeneratorAsyncGen(n, e, r, t, o);
	  return a.next().then(function (n) {
	    return n.done ? n.value : a.next();
	  });
	}
	module.exports = _regeneratorAsync, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorAsync));

var regeneratorAsyncExports = regeneratorAsync.exports;

var regeneratorKeys = {exports: {}};

(function (module) {
	function _regeneratorKeys(e) {
	  var n = Object(e),
	    r = [];
	  for (var t in n) r.unshift(t);
	  return function e() {
	    for (; r.length;) if ((t = r.pop()) in n) return e.value = t, e.done = false, e;
	    return e.done = true, e;
	  };
	}
	module.exports = _regeneratorKeys, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorKeys));

var regeneratorKeysExports = regeneratorKeys.exports;

var regeneratorValues = {exports: {}};

var _typeof = {exports: {}};

(function (module) {
	function _typeof(o) {
	  "@babel/helpers - typeof";

	  return module.exports = _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
	    return typeof o;
	  } : function (o) {
	    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
	  }, module.exports.__esModule = true, module.exports["default"] = module.exports, _typeof(o);
	}
	module.exports = _typeof, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (_typeof));

var _typeofExports = _typeof.exports;

(function (module) {
	var _typeof = _typeofExports["default"];
	function _regeneratorValues(e) {
	  if (null != e) {
	    var t = e["function" == typeof Symbol && Symbol.iterator || "@@iterator"],
	      r = 0;
	    if (t) return t.call(e);
	    if ("function" == typeof e.next) return e;
	    if (!isNaN(e.length)) return {
	      next: function next() {
	        return e && r >= e.length && (e = void 0), {
	          value: e && e[r++],
	          done: !e
	        };
	      }
	    };
	  }
	  throw new TypeError(_typeof(e) + " is not iterable");
	}
	module.exports = _regeneratorValues, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorValues));

var regeneratorValuesExports = regeneratorValues.exports;

(function (module) {
	var OverloadYield = OverloadYieldExports;
	var regenerator = regeneratorExports;
	var regeneratorAsync = regeneratorAsyncExports;
	var regeneratorAsyncGen = regeneratorAsyncGenExports;
	var regeneratorAsyncIterator = regeneratorAsyncIteratorExports;
	var regeneratorKeys = regeneratorKeysExports;
	var regeneratorValues = regeneratorValuesExports;
	function _regeneratorRuntime() {

	  var r = regenerator(),
	    e = r.m(_regeneratorRuntime),
	    t = (Object.getPrototypeOf ? Object.getPrototypeOf(e) : e.__proto__).constructor;
	  function n(r) {
	    var e = "function" == typeof r && r.constructor;
	    return !!e && (e === t || "GeneratorFunction" === (e.displayName || e.name));
	  }
	  var o = {
	    "throw": 1,
	    "return": 2,
	    "break": 3,
	    "continue": 3
	  };
	  function a(r) {
	    var e, t;
	    return function (n) {
	      e || (e = {
	        stop: function stop() {
	          return t(n.a, 2);
	        },
	        "catch": function _catch() {
	          return n.v;
	        },
	        abrupt: function abrupt(r, e) {
	          return t(n.a, o[r], e);
	        },
	        delegateYield: function delegateYield(r, o, a) {
	          return e.resultName = o, t(n.d, regeneratorValues(r), a);
	        },
	        finish: function finish(r) {
	          return t(n.f, r);
	        }
	      }, t = function t(r, _t, o) {
	        n.p = e.prev, n.n = e.next;
	        try {
	          return r(_t, o);
	        } finally {
	          e.next = n.n;
	        }
	      }), e.resultName && (e[e.resultName] = n.v, e.resultName = void 0), e.sent = n.v, e.next = n.n;
	      try {
	        return r.call(this, e);
	      } finally {
	        n.p = e.prev, n.n = e.next;
	      }
	    };
	  }
	  return (module.exports = _regeneratorRuntime = function _regeneratorRuntime() {
	    return {
	      wrap: function wrap(e, t, n, o) {
	        return r.w(a(e), t, n, o && o.reverse());
	      },
	      isGeneratorFunction: n,
	      mark: r.m,
	      awrap: function awrap(r, e) {
	        return new OverloadYield(r, e);
	      },
	      AsyncIterator: regeneratorAsyncIterator,
	      async: function async(r, e, t, o, u) {
	        return (n(e) ? regeneratorAsyncGen : regeneratorAsync)(a(r), e, t, o, u);
	      },
	      keys: regeneratorKeys,
	      values: regeneratorValues
	    };
	  }, module.exports.__esModule = true, module.exports["default"] = module.exports)();
	}
	module.exports = _regeneratorRuntime, module.exports.__esModule = true, module.exports["default"] = module.exports; 
} (regeneratorRuntime$1));

var regeneratorRuntimeExports = regeneratorRuntime$1.exports;

// TODO(Babel 8): Remove this file.

var runtime = regeneratorRuntimeExports();
var regenerator = runtime;

// Copied from https://github.com/facebook/regenerator/blob/main/packages/runtime/runtime.js#L736=
try {
  regeneratorRuntime = runtime;
} catch (accidentalStrictMode) {
  if (typeof globalThis === "object") {
    globalThis.regeneratorRuntime = runtime;
  } else {
    Function("r", "regeneratorRuntime = r")(runtime);
  }
}

var _regeneratorRuntime = /*@__PURE__*/getDefaultExportFromCjs(regenerator);

var fails$d = function (exec) {
  try {
    return !!exec();
  } catch (error) {
    return true;
  }
};

var fails$c = fails$d;

var functionBindNative = !fails$c(function () {
  // eslint-disable-next-line es/no-function-prototype-bind -- safe
  var test = (function () { /* empty */ }).bind();
  // eslint-disable-next-line no-prototype-builtins -- safe
  return typeof test != 'function' || test.hasOwnProperty('prototype');
});

var NATIVE_BIND$3 = functionBindNative;

var FunctionPrototype$2 = Function.prototype;
var call$b = FunctionPrototype$2.call;
// eslint-disable-next-line es/no-function-prototype-bind -- safe
var uncurryThisWithBind = NATIVE_BIND$3 && FunctionPrototype$2.bind.bind(call$b, call$b);

var functionUncurryThis = NATIVE_BIND$3 ? uncurryThisWithBind : function (fn) {
  return function () {
    return call$b.apply(fn, arguments);
  };
};

var uncurryThis$h = functionUncurryThis;

var toString$7 = uncurryThis$h({}.toString);
var stringSlice$3 = uncurryThis$h(''.slice);

var classofRaw$2 = function (it) {
  return stringSlice$3(toString$7(it), 8, -1);
};

var uncurryThis$g = functionUncurryThis;
var fails$b = fails$d;
var classof$6 = classofRaw$2;

var $Object$4 = Object;
var split$3 = uncurryThis$g(''.split);

// fallback for non-array-like ES3 and non-enumerable old V8 strings
var indexedObject = fails$b(function () {
  // throws an error in rhino, see https://github.com/mozilla/rhino/issues/346
  // eslint-disable-next-line no-prototype-builtins -- safe
  return !$Object$4('z').propertyIsEnumerable(0);
}) ? function (it) {
  return classof$6(it) === 'String' ? split$3(it, '') : $Object$4(it);
} : $Object$4;

// we can't use just `it == null` since of `document.all` special case
// https://tc39.es/ecma262/#sec-IsHTMLDDA-internal-slot-aec
var isNullOrUndefined$3 = function (it) {
  return it === null || it === undefined;
};

var isNullOrUndefined$2 = isNullOrUndefined$3;

var $TypeError$8 = TypeError;

// `RequireObjectCoercible` abstract operation
// https://tc39.es/ecma262/#sec-requireobjectcoercible
var requireObjectCoercible$3 = function (it) {
  if (isNullOrUndefined$2(it)) throw new $TypeError$8("Can't call method on " + it);
  return it;
};

// toObject with fallback for non-array-like ES3 strings
var IndexedObject$1 = indexedObject;
var requireObjectCoercible$2 = requireObjectCoercible$3;

var toIndexedObject$5 = function (it) {
  return IndexedObject$1(requireObjectCoercible$2(it));
};

var iterators = {};

var check = function (it) {
  return it && it.Math === Math && it;
};

// https://github.com/zloirock/core-js/issues/86#issuecomment-115759028
var globalThis_1 =
  // eslint-disable-next-line es/no-global-this -- safe
  check(typeof globalThis == 'object' && globalThis) ||
  check(typeof window == 'object' && window) ||
  // eslint-disable-next-line no-restricted-globals -- safe
  check(typeof self == 'object' && self) ||
  check(typeof commonjsGlobal == 'object' && commonjsGlobal) ||
  check(typeof commonjsGlobal == 'object' && commonjsGlobal) ||
  // eslint-disable-next-line no-new-func -- fallback
  (function () { return this; })() || Function('return this')();

// https://tc39.es/ecma262/#sec-IsHTMLDDA-internal-slot
var documentAll = typeof document == 'object' && document.all;

// `IsCallable` abstract operation
// https://tc39.es/ecma262/#sec-iscallable
// eslint-disable-next-line unicorn/no-typeof-undefined -- required for testing
var isCallable$e = typeof documentAll == 'undefined' && documentAll !== undefined ? function (argument) {
  return typeof argument == 'function' || argument === documentAll;
} : function (argument) {
  return typeof argument == 'function';
};

var globalThis$f = globalThis_1;
var isCallable$d = isCallable$e;

var WeakMap$1 = globalThis$f.WeakMap;

var weakMapBasicDetection = isCallable$d(WeakMap$1) && /native code/.test(String(WeakMap$1));

var isCallable$c = isCallable$e;

var isObject$7 = function (it) {
  return typeof it == 'object' ? it !== null : isCallable$c(it);
};

var fails$a = fails$d;

// Detect IE8's incomplete defineProperty implementation
var descriptors = !fails$a(function () {
  // eslint-disable-next-line es/no-object-defineproperty -- required for testing
  return Object.defineProperty({}, 1, { get: function () { return 7; } })[1] !== 7;
});

var objectDefineProperty = {};

var globalThis$e = globalThis_1;
var isObject$6 = isObject$7;

var document$1 = globalThis$e.document;
// typeof document.createElement is 'object' in old IE
var EXISTS$1 = isObject$6(document$1) && isObject$6(document$1.createElement);

var documentCreateElement$1 = function (it) {
  return EXISTS$1 ? document$1.createElement(it) : {};
};

var DESCRIPTORS$c = descriptors;
var fails$9 = fails$d;
var createElement = documentCreateElement$1;

// Thanks to IE8 for its funny defineProperty
var ie8DomDefine = !DESCRIPTORS$c && !fails$9(function () {
  // eslint-disable-next-line es/no-object-defineproperty -- required for testing
  return Object.defineProperty(createElement('div'), 'a', {
    get: function () { return 7; }
  }).a !== 7;
});

var DESCRIPTORS$b = descriptors;
var fails$8 = fails$d;

// V8 ~ Chrome 36-
// https://bugs.chromium.org/p/v8/issues/detail?id=3334
var v8PrototypeDefineBug = DESCRIPTORS$b && fails$8(function () {
  // eslint-disable-next-line es/no-object-defineproperty -- required for testing
  return Object.defineProperty(function () { /* empty */ }, 'prototype', {
    value: 42,
    writable: false
  }).prototype !== 42;
});

var isObject$5 = isObject$7;

var $String$3 = String;
var $TypeError$7 = TypeError;

// `Assert: Type(argument) is Object`
var anObject$7 = function (argument) {
  if (isObject$5(argument)) return argument;
  throw new $TypeError$7($String$3(argument) + ' is not an object');
};

var NATIVE_BIND$2 = functionBindNative;

var call$a = Function.prototype.call;
// eslint-disable-next-line es/no-function-prototype-bind -- safe
var functionCall = NATIVE_BIND$2 ? call$a.bind(call$a) : function () {
  return call$a.apply(call$a, arguments);
};

var path$4 = {};

var path$3 = path$4;
var globalThis$d = globalThis_1;
var isCallable$b = isCallable$e;

var aFunction = function (variable) {
  return isCallable$b(variable) ? variable : undefined;
};

var getBuiltIn$6 = function (namespace, method) {
  return arguments.length < 2 ? aFunction(path$3[namespace]) || aFunction(globalThis$d[namespace])
    : path$3[namespace] && path$3[namespace][method] || globalThis$d[namespace] && globalThis$d[namespace][method];
};

var uncurryThis$f = functionUncurryThis;

var objectIsPrototypeOf = uncurryThis$f({}.isPrototypeOf);

var globalThis$c = globalThis_1;

var navigator = globalThis$c.navigator;
var userAgent$1 = navigator && navigator.userAgent;

var environmentUserAgent = userAgent$1 ? String(userAgent$1) : '';

var globalThis$b = globalThis_1;
var userAgent = environmentUserAgent;

var process = globalThis$b.process;
var Deno = globalThis$b.Deno;
var versions = process && process.versions || Deno && Deno.version;
var v8 = versions && versions.v8;
var match, version;

if (v8) {
  match = v8.split('.');
  // in old Chrome, versions of V8 isn't V8 = Chrome / 10
  // but their correct versions are not interesting for us
  version = match[0] > 0 && match[0] < 4 ? 1 : +(match[0] + match[1]);
}

// BrowserFS NodeJS `process` polyfill incorrectly set `.v8` to `0.0`
// so check `userAgent` even if `.v8` exists, but 0
if (!version && userAgent) {
  match = userAgent.match(/Edge\/(\d+)/);
  if (!match || match[1] >= 74) {
    match = userAgent.match(/Chrome\/(\d+)/);
    if (match) version = +match[1];
  }
}

var environmentV8Version = version;

/* eslint-disable es/no-symbol -- required for testing */
var V8_VERSION = environmentV8Version;
var fails$7 = fails$d;
var globalThis$a = globalThis_1;

var $String$2 = globalThis$a.String;

// eslint-disable-next-line es/no-object-getownpropertysymbols -- required for testing
var symbolConstructorDetection = !!Object.getOwnPropertySymbols && !fails$7(function () {
  var symbol = Symbol('symbol detection');
  // Chrome 38 Symbol has incorrect toString conversion
  // `get-own-property-symbols` polyfill symbols converted to object are not Symbol instances
  // nb: Do not call `String` directly to avoid this being optimized out to `symbol+''` which will,
  // of course, fail.
  return !$String$2(symbol) || !(Object(symbol) instanceof Symbol) ||
    // Chrome 38-40 symbols are not inherited from DOM collections prototypes to instances
    !Symbol.sham && V8_VERSION && V8_VERSION < 41;
});

/* eslint-disable es/no-symbol -- required for testing */
var NATIVE_SYMBOL$1 = symbolConstructorDetection;

var useSymbolAsUid = NATIVE_SYMBOL$1 &&
  !Symbol.sham &&
  typeof Symbol.iterator == 'symbol';

var getBuiltIn$5 = getBuiltIn$6;
var isCallable$a = isCallable$e;
var isPrototypeOf$1 = objectIsPrototypeOf;
var USE_SYMBOL_AS_UID$1 = useSymbolAsUid;

var $Object$3 = Object;

var isSymbol$2 = USE_SYMBOL_AS_UID$1 ? function (it) {
  return typeof it == 'symbol';
} : function (it) {
  var $Symbol = getBuiltIn$5('Symbol');
  return isCallable$a($Symbol) && isPrototypeOf$1($Symbol.prototype, $Object$3(it));
};

var $String$1 = String;

var tryToString$2 = function (argument) {
  try {
    return $String$1(argument);
  } catch (error) {
    return 'Object';
  }
};

var isCallable$9 = isCallable$e;
var tryToString$1 = tryToString$2;

var $TypeError$6 = TypeError;

// `Assert: IsCallable(argument) is true`
var aCallable$3 = function (argument) {
  if (isCallable$9(argument)) return argument;
  throw new $TypeError$6(tryToString$1(argument) + ' is not a function');
};

var aCallable$2 = aCallable$3;
var isNullOrUndefined$1 = isNullOrUndefined$3;

// `GetMethod` abstract operation
// https://tc39.es/ecma262/#sec-getmethod
var getMethod$3 = function (V, P) {
  var func = V[P];
  return isNullOrUndefined$1(func) ? undefined : aCallable$2(func);
};

var call$9 = functionCall;
var isCallable$8 = isCallable$e;
var isObject$4 = isObject$7;

var $TypeError$5 = TypeError;

// `OrdinaryToPrimitive` abstract operation
// https://tc39.es/ecma262/#sec-ordinarytoprimitive
var ordinaryToPrimitive$1 = function (input, pref) {
  var fn, val;
  if (pref === 'string' && isCallable$8(fn = input.toString) && !isObject$4(val = call$9(fn, input))) return val;
  if (isCallable$8(fn = input.valueOf) && !isObject$4(val = call$9(fn, input))) return val;
  if (pref !== 'string' && isCallable$8(fn = input.toString) && !isObject$4(val = call$9(fn, input))) return val;
  throw new $TypeError$5("Can't convert object to primitive value");
};

var sharedStore = {exports: {}};

var isPure = true;

var globalThis$9 = globalThis_1;

// eslint-disable-next-line es/no-object-defineproperty -- safe
var defineProperty$3 = Object.defineProperty;

var defineGlobalProperty$1 = function (key, value) {
  try {
    defineProperty$3(globalThis$9, key, { value: value, configurable: true, writable: true });
  } catch (error) {
    globalThis$9[key] = value;
  } return value;
};

var globalThis$8 = globalThis_1;
var defineGlobalProperty = defineGlobalProperty$1;

var SHARED = '__core-js_shared__';
var store$3 = sharedStore.exports = globalThis$8[SHARED] || defineGlobalProperty(SHARED, {});

(store$3.versions || (store$3.versions = [])).push({
  version: '3.46.0',
  mode: 'pure' ,
  copyright: '© 2014-2025 Denis Pushkarev (zloirock.ru), 2025 CoreJS Company (core-js.io)',
  license: 'https://github.com/zloirock/core-js/blob/v3.46.0/LICENSE',
  source: 'https://github.com/zloirock/core-js'
});

var sharedStoreExports = sharedStore.exports;

var store$2 = sharedStoreExports;

var shared$3 = function (key, value) {
  return store$2[key] || (store$2[key] = value || {});
};

var requireObjectCoercible$1 = requireObjectCoercible$3;

var $Object$2 = Object;

// `ToObject` abstract operation
// https://tc39.es/ecma262/#sec-toobject
var toObject$4 = function (argument) {
  return $Object$2(requireObjectCoercible$1(argument));
};

var uncurryThis$e = functionUncurryThis;
var toObject$3 = toObject$4;

var hasOwnProperty = uncurryThis$e({}.hasOwnProperty);

// `HasOwnProperty` abstract operation
// https://tc39.es/ecma262/#sec-hasownproperty
// eslint-disable-next-line es/no-object-hasown -- safe
var hasOwnProperty_1 = Object.hasOwn || function hasOwn(it, key) {
  return hasOwnProperty(toObject$3(it), key);
};

var uncurryThis$d = functionUncurryThis;

var id = 0;
var postfix = Math.random();
var toString$6 = uncurryThis$d(1.1.toString);

var uid$2 = function (key) {
  return 'Symbol(' + (key === undefined ? '' : key) + ')_' + toString$6(++id + postfix, 36);
};

var globalThis$7 = globalThis_1;
var shared$2 = shared$3;
var hasOwn$9 = hasOwnProperty_1;
var uid$1 = uid$2;
var NATIVE_SYMBOL = symbolConstructorDetection;
var USE_SYMBOL_AS_UID = useSymbolAsUid;

var Symbol$1 = globalThis$7.Symbol;
var WellKnownSymbolsStore = shared$2('wks');
var createWellKnownSymbol = USE_SYMBOL_AS_UID ? Symbol$1['for'] || Symbol$1 : Symbol$1 && Symbol$1.withoutSetter || uid$1;

var wellKnownSymbol$a = function (name) {
  if (!hasOwn$9(WellKnownSymbolsStore, name)) {
    WellKnownSymbolsStore[name] = NATIVE_SYMBOL && hasOwn$9(Symbol$1, name)
      ? Symbol$1[name]
      : createWellKnownSymbol('Symbol.' + name);
  } return WellKnownSymbolsStore[name];
};

var call$8 = functionCall;
var isObject$3 = isObject$7;
var isSymbol$1 = isSymbol$2;
var getMethod$2 = getMethod$3;
var ordinaryToPrimitive = ordinaryToPrimitive$1;
var wellKnownSymbol$9 = wellKnownSymbol$a;

var $TypeError$4 = TypeError;
var TO_PRIMITIVE = wellKnownSymbol$9('toPrimitive');

// `ToPrimitive` abstract operation
// https://tc39.es/ecma262/#sec-toprimitive
var toPrimitive$1 = function (input, pref) {
  if (!isObject$3(input) || isSymbol$1(input)) return input;
  var exoticToPrim = getMethod$2(input, TO_PRIMITIVE);
  var result;
  if (exoticToPrim) {
    if (pref === undefined) pref = 'default';
    result = call$8(exoticToPrim, input, pref);
    if (!isObject$3(result) || isSymbol$1(result)) return result;
    throw new $TypeError$4("Can't convert object to primitive value");
  }
  if (pref === undefined) pref = 'number';
  return ordinaryToPrimitive(input, pref);
};

var toPrimitive = toPrimitive$1;
var isSymbol = isSymbol$2;

// `ToPropertyKey` abstract operation
// https://tc39.es/ecma262/#sec-topropertykey
var toPropertyKey$2 = function (argument) {
  var key = toPrimitive(argument, 'string');
  return isSymbol(key) ? key : key + '';
};

var DESCRIPTORS$a = descriptors;
var IE8_DOM_DEFINE$1 = ie8DomDefine;
var V8_PROTOTYPE_DEFINE_BUG$1 = v8PrototypeDefineBug;
var anObject$6 = anObject$7;
var toPropertyKey$1 = toPropertyKey$2;

var $TypeError$3 = TypeError;
// eslint-disable-next-line es/no-object-defineproperty -- safe
var $defineProperty = Object.defineProperty;
// eslint-disable-next-line es/no-object-getownpropertydescriptor -- safe
var $getOwnPropertyDescriptor$1 = Object.getOwnPropertyDescriptor;
var ENUMERABLE = 'enumerable';
var CONFIGURABLE$1 = 'configurable';
var WRITABLE = 'writable';

// `Object.defineProperty` method
// https://tc39.es/ecma262/#sec-object.defineproperty
objectDefineProperty.f = DESCRIPTORS$a ? V8_PROTOTYPE_DEFINE_BUG$1 ? function defineProperty(O, P, Attributes) {
  anObject$6(O);
  P = toPropertyKey$1(P);
  anObject$6(Attributes);
  if (typeof O === 'function' && P === 'prototype' && 'value' in Attributes && WRITABLE in Attributes && !Attributes[WRITABLE]) {
    var current = $getOwnPropertyDescriptor$1(O, P);
    if (current && current[WRITABLE]) {
      O[P] = Attributes.value;
      Attributes = {
        configurable: CONFIGURABLE$1 in Attributes ? Attributes[CONFIGURABLE$1] : current[CONFIGURABLE$1],
        enumerable: ENUMERABLE in Attributes ? Attributes[ENUMERABLE] : current[ENUMERABLE],
        writable: false
      };
    }
  } return $defineProperty(O, P, Attributes);
} : $defineProperty : function defineProperty(O, P, Attributes) {
  anObject$6(O);
  P = toPropertyKey$1(P);
  anObject$6(Attributes);
  if (IE8_DOM_DEFINE$1) try {
    return $defineProperty(O, P, Attributes);
  } catch (error) { /* empty */ }
  if ('get' in Attributes || 'set' in Attributes) throw new $TypeError$3('Accessors not supported');
  if ('value' in Attributes) O[P] = Attributes.value;
  return O;
};

var createPropertyDescriptor$5 = function (bitmap, value) {
  return {
    enumerable: !(bitmap & 1),
    configurable: !(bitmap & 2),
    writable: !(bitmap & 4),
    value: value
  };
};

var DESCRIPTORS$9 = descriptors;
var definePropertyModule$2 = objectDefineProperty;
var createPropertyDescriptor$4 = createPropertyDescriptor$5;

var createNonEnumerableProperty$4 = DESCRIPTORS$9 ? function (object, key, value) {
  return definePropertyModule$2.f(object, key, createPropertyDescriptor$4(1, value));
} : function (object, key, value) {
  object[key] = value;
  return object;
};

var shared$1 = shared$3;
var uid = uid$2;

var keys = shared$1('keys');

var sharedKey$3 = function (key) {
  return keys[key] || (keys[key] = uid(key));
};

var hiddenKeys$3 = {};

var NATIVE_WEAK_MAP = weakMapBasicDetection;
var globalThis$6 = globalThis_1;
var isObject$2 = isObject$7;
var createNonEnumerableProperty$3 = createNonEnumerableProperty$4;
var hasOwn$8 = hasOwnProperty_1;
var shared = sharedStoreExports;
var sharedKey$2 = sharedKey$3;
var hiddenKeys$2 = hiddenKeys$3;

var OBJECT_ALREADY_INITIALIZED = 'Object already initialized';
var TypeError$3 = globalThis$6.TypeError;
var WeakMap = globalThis$6.WeakMap;
var set, get, has;

var enforce = function (it) {
  return has(it) ? get(it) : set(it, {});
};

var getterFor = function (TYPE) {
  return function (it) {
    var state;
    if (!isObject$2(it) || (state = get(it)).type !== TYPE) {
      throw new TypeError$3('Incompatible receiver, ' + TYPE + ' required');
    } return state;
  };
};

if (NATIVE_WEAK_MAP || shared.state) {
  var store$1 = shared.state || (shared.state = new WeakMap());
  /* eslint-disable no-self-assign -- prototype methods protection */
  store$1.get = store$1.get;
  store$1.has = store$1.has;
  store$1.set = store$1.set;
  /* eslint-enable no-self-assign -- prototype methods protection */
  set = function (it, metadata) {
    if (store$1.has(it)) throw new TypeError$3(OBJECT_ALREADY_INITIALIZED);
    metadata.facade = it;
    store$1.set(it, metadata);
    return metadata;
  };
  get = function (it) {
    return store$1.get(it) || {};
  };
  has = function (it) {
    return store$1.has(it);
  };
} else {
  var STATE = sharedKey$2('state');
  hiddenKeys$2[STATE] = true;
  set = function (it, metadata) {
    if (hasOwn$8(it, STATE)) throw new TypeError$3(OBJECT_ALREADY_INITIALIZED);
    metadata.facade = it;
    createNonEnumerableProperty$3(it, STATE, metadata);
    return metadata;
  };
  get = function (it) {
    return hasOwn$8(it, STATE) ? it[STATE] : {};
  };
  has = function (it) {
    return hasOwn$8(it, STATE);
  };
}

var internalState = {
  set: set,
  get: get,
  has: has,
  enforce: enforce,
  getterFor: getterFor
};

var NATIVE_BIND$1 = functionBindNative;

var FunctionPrototype$1 = Function.prototype;
var apply$1 = FunctionPrototype$1.apply;
var call$7 = FunctionPrototype$1.call;

// eslint-disable-next-line es/no-function-prototype-bind, es/no-reflect -- safe
var functionApply = typeof Reflect == 'object' && Reflect.apply || (NATIVE_BIND$1 ? call$7.bind(apply$1) : function () {
  return call$7.apply(apply$1, arguments);
});

var classofRaw$1 = classofRaw$2;
var uncurryThis$c = functionUncurryThis;

var functionUncurryThisClause = function (fn) {
  // Nashorn bug:
  //   https://github.com/zloirock/core-js/issues/1128
  //   https://github.com/zloirock/core-js/issues/1130
  if (classofRaw$1(fn) === 'Function') return uncurryThis$c(fn);
};

var objectGetOwnPropertyDescriptor = {};

var objectPropertyIsEnumerable = {};

var $propertyIsEnumerable = {}.propertyIsEnumerable;
// eslint-disable-next-line es/no-object-getownpropertydescriptor -- safe
var getOwnPropertyDescriptor$2 = Object.getOwnPropertyDescriptor;

// Nashorn ~ JDK8 bug
var NASHORN_BUG = getOwnPropertyDescriptor$2 && !$propertyIsEnumerable.call({ 1: 2 }, 1);

// `Object.prototype.propertyIsEnumerable` method implementation
// https://tc39.es/ecma262/#sec-object.prototype.propertyisenumerable
objectPropertyIsEnumerable.f = NASHORN_BUG ? function propertyIsEnumerable(V) {
  var descriptor = getOwnPropertyDescriptor$2(this, V);
  return !!descriptor && descriptor.enumerable;
} : $propertyIsEnumerable;

var DESCRIPTORS$8 = descriptors;
var call$6 = functionCall;
var propertyIsEnumerableModule$1 = objectPropertyIsEnumerable;
var createPropertyDescriptor$3 = createPropertyDescriptor$5;
var toIndexedObject$4 = toIndexedObject$5;
var toPropertyKey = toPropertyKey$2;
var hasOwn$7 = hasOwnProperty_1;
var IE8_DOM_DEFINE = ie8DomDefine;

// eslint-disable-next-line es/no-object-getownpropertydescriptor -- safe
var $getOwnPropertyDescriptor = Object.getOwnPropertyDescriptor;

// `Object.getOwnPropertyDescriptor` method
// https://tc39.es/ecma262/#sec-object.getownpropertydescriptor
objectGetOwnPropertyDescriptor.f = DESCRIPTORS$8 ? $getOwnPropertyDescriptor : function getOwnPropertyDescriptor(O, P) {
  O = toIndexedObject$4(O);
  P = toPropertyKey(P);
  if (IE8_DOM_DEFINE) try {
    return $getOwnPropertyDescriptor(O, P);
  } catch (error) { /* empty */ }
  if (hasOwn$7(O, P)) return createPropertyDescriptor$3(!call$6(propertyIsEnumerableModule$1.f, O, P), O[P]);
};

var fails$6 = fails$d;
var isCallable$7 = isCallable$e;

var replacement = /#|\.prototype\./;

var isForced$1 = function (feature, detection) {
  var value = data[normalize(feature)];
  return value === POLYFILL ? true
    : value === NATIVE ? false
    : isCallable$7(detection) ? fails$6(detection)
    : !!detection;
};

var normalize = isForced$1.normalize = function (string) {
  return String(string).replace(replacement, '.').toLowerCase();
};

var data = isForced$1.data = {};
var NATIVE = isForced$1.NATIVE = 'N';
var POLYFILL = isForced$1.POLYFILL = 'P';

var isForced_1 = isForced$1;

var uncurryThis$b = functionUncurryThisClause;
var aCallable$1 = aCallable$3;
var NATIVE_BIND = functionBindNative;

var bind$4 = uncurryThis$b(uncurryThis$b.bind);

// optional / simple context binding
var functionBindContext = function (fn, that) {
  aCallable$1(fn);
  return that === undefined ? fn : NATIVE_BIND ? bind$4(fn, that) : function (/* ...args */) {
    return fn.apply(that, arguments);
  };
};

var globalThis$5 = globalThis_1;
var apply = functionApply;
var uncurryThis$a = functionUncurryThisClause;
var isCallable$6 = isCallable$e;
var getOwnPropertyDescriptor$1 = objectGetOwnPropertyDescriptor.f;
var isForced = isForced_1;
var path$2 = path$4;
var bind$3 = functionBindContext;
var createNonEnumerableProperty$2 = createNonEnumerableProperty$4;
var hasOwn$6 = hasOwnProperty_1;
// add debugging info


var wrapConstructor = function (NativeConstructor) {
  var Wrapper = function (a, b, c) {
    if (this instanceof Wrapper) {
      switch (arguments.length) {
        case 0: return new NativeConstructor();
        case 1: return new NativeConstructor(a);
        case 2: return new NativeConstructor(a, b);
      } return new NativeConstructor(a, b, c);
    } return apply(NativeConstructor, this, arguments);
  };
  Wrapper.prototype = NativeConstructor.prototype;
  return Wrapper;
};

/*
  options.target         - name of the target object
  options.global         - target is the global object
  options.stat           - export as static methods of target
  options.proto          - export as prototype methods of target
  options.real           - real prototype method for the `pure` version
  options.forced         - export even if the native feature is available
  options.bind           - bind methods to the target, required for the `pure` version
  options.wrap           - wrap constructors to preventing global pollution, required for the `pure` version
  options.unsafe         - use the simple assignment of property instead of delete + defineProperty
  options.sham           - add a flag to not completely full polyfills
  options.enumerable     - export as enumerable property
  options.dontCallGetSet - prevent calling a getter on target
  options.name           - the .name of the function if it does not match the key
*/
var _export = function (options, source) {
  var TARGET = options.target;
  var GLOBAL = options.global;
  var STATIC = options.stat;
  var PROTO = options.proto;

  var nativeSource = GLOBAL ? globalThis$5 : STATIC ? globalThis$5[TARGET] : globalThis$5[TARGET] && globalThis$5[TARGET].prototype;

  var target = GLOBAL ? path$2 : path$2[TARGET] || createNonEnumerableProperty$2(path$2, TARGET, {})[TARGET];
  var targetPrototype = target.prototype;

  var FORCED, USE_NATIVE, VIRTUAL_PROTOTYPE;
  var key, sourceProperty, targetProperty, nativeProperty, resultProperty, descriptor;

  for (key in source) {
    FORCED = isForced(GLOBAL ? key : TARGET + (STATIC ? '.' : '#') + key, options.forced);
    // contains in native
    USE_NATIVE = !FORCED && nativeSource && hasOwn$6(nativeSource, key);

    targetProperty = target[key];

    if (USE_NATIVE) if (options.dontCallGetSet) {
      descriptor = getOwnPropertyDescriptor$1(nativeSource, key);
      nativeProperty = descriptor && descriptor.value;
    } else nativeProperty = nativeSource[key];

    // export native or implementation
    sourceProperty = (USE_NATIVE && nativeProperty) ? nativeProperty : source[key];

    if (!FORCED && !PROTO && typeof targetProperty == typeof sourceProperty) continue;

    // bind methods to global for calling from export context
    if (options.bind && USE_NATIVE) resultProperty = bind$3(sourceProperty, globalThis$5);
    // wrap global constructors for prevent changes in this version
    else if (options.wrap && USE_NATIVE) resultProperty = wrapConstructor(sourceProperty);
    // make static versions for prototype methods
    else if (PROTO && isCallable$6(sourceProperty)) resultProperty = uncurryThis$a(sourceProperty);
    // default case
    else resultProperty = sourceProperty;

    // add a flag to not completely full polyfills
    if (options.sham || (sourceProperty && sourceProperty.sham) || (targetProperty && targetProperty.sham)) {
      createNonEnumerableProperty$2(resultProperty, 'sham', true);
    }

    createNonEnumerableProperty$2(target, key, resultProperty);

    if (PROTO) {
      VIRTUAL_PROTOTYPE = TARGET + 'Prototype';
      if (!hasOwn$6(path$2, VIRTUAL_PROTOTYPE)) {
        createNonEnumerableProperty$2(path$2, VIRTUAL_PROTOTYPE, {});
      }
      // export virtual prototype methods
      createNonEnumerableProperty$2(path$2[VIRTUAL_PROTOTYPE], key, sourceProperty);
      // export real prototype methods
      if (options.real && targetPrototype && (FORCED || !targetPrototype[key])) {
        createNonEnumerableProperty$2(targetPrototype, key, sourceProperty);
      }
    }
  }
};

var DESCRIPTORS$7 = descriptors;
var hasOwn$5 = hasOwnProperty_1;

var FunctionPrototype = Function.prototype;
// eslint-disable-next-line es/no-object-getownpropertydescriptor -- safe
var getDescriptor = DESCRIPTORS$7 && Object.getOwnPropertyDescriptor;

var EXISTS = hasOwn$5(FunctionPrototype, 'name');
// additional protection from minified / mangled / dropped function names
var PROPER = EXISTS && (function something() { /* empty */ }).name === 'something';
var CONFIGURABLE = EXISTS && (!DESCRIPTORS$7 || (DESCRIPTORS$7 && getDescriptor(FunctionPrototype, 'name').configurable));

var functionName = {
  PROPER: PROPER,
  CONFIGURABLE: CONFIGURABLE
};

var objectDefineProperties = {};

var ceil = Math.ceil;
var floor$3 = Math.floor;

// `Math.trunc` method
// https://tc39.es/ecma262/#sec-math.trunc
// eslint-disable-next-line es/no-math-trunc -- safe
var mathTrunc = Math.trunc || function trunc(x) {
  var n = +x;
  return (n > 0 ? floor$3 : ceil)(n);
};

var trunc = mathTrunc;

// `ToIntegerOrInfinity` abstract operation
// https://tc39.es/ecma262/#sec-tointegerorinfinity
var toIntegerOrInfinity$3 = function (argument) {
  var number = +argument;
  // eslint-disable-next-line no-self-compare -- NaN check
  return number !== number || number === 0 ? 0 : trunc(number);
};

var toIntegerOrInfinity$2 = toIntegerOrInfinity$3;

var max = Math.max;
var min$1 = Math.min;

// Helper for a popular repeating case of the spec:
// Let integer be ? ToInteger(index).
// If integer < 0, let result be max((length + integer), 0); else let result be min(integer, length).
var toAbsoluteIndex$2 = function (index, length) {
  var integer = toIntegerOrInfinity$2(index);
  return integer < 0 ? max(integer + length, 0) : min$1(integer, length);
};

var toIntegerOrInfinity$1 = toIntegerOrInfinity$3;

var min = Math.min;

// `ToLength` abstract operation
// https://tc39.es/ecma262/#sec-tolength
var toLength$1 = function (argument) {
  var len = toIntegerOrInfinity$1(argument);
  return len > 0 ? min(len, 0x1FFFFFFFFFFFFF) : 0; // 2 ** 53 - 1 == 9007199254740991
};

var toLength = toLength$1;

// `LengthOfArrayLike` abstract operation
// https://tc39.es/ecma262/#sec-lengthofarraylike
var lengthOfArrayLike$2 = function (obj) {
  return toLength(obj.length);
};

var toIndexedObject$3 = toIndexedObject$5;
var toAbsoluteIndex$1 = toAbsoluteIndex$2;
var lengthOfArrayLike$1 = lengthOfArrayLike$2;

// `Array.prototype.{ indexOf, includes }` methods implementation
var createMethod$1 = function (IS_INCLUDES) {
  return function ($this, el, fromIndex) {
    var O = toIndexedObject$3($this);
    var length = lengthOfArrayLike$1(O);
    if (length === 0) return !IS_INCLUDES && -1;
    var index = toAbsoluteIndex$1(fromIndex, length);
    var value;
    // Array#includes uses SameValueZero equality algorithm
    // eslint-disable-next-line no-self-compare -- NaN check
    if (IS_INCLUDES && el !== el) while (length > index) {
      value = O[index++];
      // eslint-disable-next-line no-self-compare -- NaN check
      if (value !== value) return true;
    // Array#indexOf ignores holes, Array#includes - not
    } else for (;length > index; index++) {
      if ((IS_INCLUDES || index in O) && O[index] === el) return IS_INCLUDES || index || 0;
    } return !IS_INCLUDES && -1;
  };
};

var arrayIncludes = {
  // `Array.prototype.indexOf` method
  // https://tc39.es/ecma262/#sec-array.prototype.indexof
  indexOf: createMethod$1(false)
};

var uncurryThis$9 = functionUncurryThis;
var hasOwn$4 = hasOwnProperty_1;
var toIndexedObject$2 = toIndexedObject$5;
var indexOf = arrayIncludes.indexOf;
var hiddenKeys$1 = hiddenKeys$3;

var push$3 = uncurryThis$9([].push);

var objectKeysInternal = function (object, names) {
  var O = toIndexedObject$2(object);
  var i = 0;
  var result = [];
  var key;
  for (key in O) !hasOwn$4(hiddenKeys$1, key) && hasOwn$4(O, key) && push$3(result, key);
  // Don't enum bug & hidden keys
  while (names.length > i) if (hasOwn$4(O, key = names[i++])) {
    ~indexOf(result, key) || push$3(result, key);
  }
  return result;
};

// IE8- don't enum bug keys
var enumBugKeys$2 = [
  'constructor',
  'hasOwnProperty',
  'isPrototypeOf',
  'propertyIsEnumerable',
  'toLocaleString',
  'toString',
  'valueOf'
];

var internalObjectKeys = objectKeysInternal;
var enumBugKeys$1 = enumBugKeys$2;

// `Object.keys` method
// https://tc39.es/ecma262/#sec-object.keys
// eslint-disable-next-line es/no-object-keys -- safe
var objectKeys$2 = Object.keys || function keys(O) {
  return internalObjectKeys(O, enumBugKeys$1);
};

var DESCRIPTORS$6 = descriptors;
var V8_PROTOTYPE_DEFINE_BUG = v8PrototypeDefineBug;
var definePropertyModule$1 = objectDefineProperty;
var anObject$5 = anObject$7;
var toIndexedObject$1 = toIndexedObject$5;
var objectKeys$1 = objectKeys$2;

// `Object.defineProperties` method
// https://tc39.es/ecma262/#sec-object.defineproperties
// eslint-disable-next-line es/no-object-defineproperties -- safe
objectDefineProperties.f = DESCRIPTORS$6 && !V8_PROTOTYPE_DEFINE_BUG ? Object.defineProperties : function defineProperties(O, Properties) {
  anObject$5(O);
  var props = toIndexedObject$1(Properties);
  var keys = objectKeys$1(Properties);
  var length = keys.length;
  var index = 0;
  var key;
  while (length > index) definePropertyModule$1.f(O, key = keys[index++], props[key]);
  return O;
};

var getBuiltIn$4 = getBuiltIn$6;

var html$1 = getBuiltIn$4('document', 'documentElement');

/* global ActiveXObject -- old IE, WSH */
var anObject$4 = anObject$7;
var definePropertiesModule = objectDefineProperties;
var enumBugKeys = enumBugKeys$2;
var hiddenKeys = hiddenKeys$3;
var html = html$1;
var documentCreateElement = documentCreateElement$1;
var sharedKey$1 = sharedKey$3;

var GT = '>';
var LT = '<';
var PROTOTYPE = 'prototype';
var SCRIPT = 'script';
var IE_PROTO$1 = sharedKey$1('IE_PROTO');

var EmptyConstructor = function () { /* empty */ };

var scriptTag = function (content) {
  return LT + SCRIPT + GT + content + LT + '/' + SCRIPT + GT;
};

// Create object with fake `null` prototype: use ActiveX Object with cleared prototype
var NullProtoObjectViaActiveX = function (activeXDocument) {
  activeXDocument.write(scriptTag(''));
  activeXDocument.close();
  var temp = activeXDocument.parentWindow.Object;
  // eslint-disable-next-line no-useless-assignment -- avoid memory leak
  activeXDocument = null;
  return temp;
};

// Create object with fake `null` prototype: use iframe Object with cleared prototype
var NullProtoObjectViaIFrame = function () {
  // Thrash, waste and sodomy: IE GC bug
  var iframe = documentCreateElement('iframe');
  var JS = 'java' + SCRIPT + ':';
  var iframeDocument;
  iframe.style.display = 'none';
  html.appendChild(iframe);
  // https://github.com/zloirock/core-js/issues/475
  iframe.src = String(JS);
  iframeDocument = iframe.contentWindow.document;
  iframeDocument.open();
  iframeDocument.write(scriptTag('document.F=Object'));
  iframeDocument.close();
  return iframeDocument.F;
};

// Check for document.domain and active x support
// No need to use active x approach when document.domain is not set
// see https://github.com/es-shims/es5-shim/issues/150
// variation of https://github.com/kitcambridge/es5-shim/commit/4f738ac066346
// avoid IE GC bug
var activeXDocument;
var NullProtoObject = function () {
  try {
    activeXDocument = new ActiveXObject('htmlfile');
  } catch (error) { /* ignore */ }
  NullProtoObject = typeof document != 'undefined'
    ? document.domain && activeXDocument
      ? NullProtoObjectViaActiveX(activeXDocument) // old IE
      : NullProtoObjectViaIFrame()
    : NullProtoObjectViaActiveX(activeXDocument); // WSH
  var length = enumBugKeys.length;
  while (length--) delete NullProtoObject[PROTOTYPE][enumBugKeys[length]];
  return NullProtoObject();
};

hiddenKeys[IE_PROTO$1] = true;

// `Object.create` method
// https://tc39.es/ecma262/#sec-object.create
// eslint-disable-next-line es/no-object-create -- safe
var objectCreate = Object.create || function create(O, Properties) {
  var result;
  if (O !== null) {
    EmptyConstructor[PROTOTYPE] = anObject$4(O);
    result = new EmptyConstructor();
    EmptyConstructor[PROTOTYPE] = null;
    // add "__proto__" for Object.getPrototypeOf polyfill
    result[IE_PROTO$1] = O;
  } else result = NullProtoObject();
  return Properties === undefined ? result : definePropertiesModule.f(result, Properties);
};

var fails$5 = fails$d;

var correctPrototypeGetter = !fails$5(function () {
  function F() { /* empty */ }
  F.prototype.constructor = null;
  // eslint-disable-next-line es/no-object-getprototypeof -- required for testing
  return Object.getPrototypeOf(new F()) !== F.prototype;
});

var hasOwn$3 = hasOwnProperty_1;
var isCallable$5 = isCallable$e;
var toObject$2 = toObject$4;
var sharedKey = sharedKey$3;
var CORRECT_PROTOTYPE_GETTER = correctPrototypeGetter;

var IE_PROTO = sharedKey('IE_PROTO');
var $Object$1 = Object;
var ObjectPrototype = $Object$1.prototype;

// `Object.getPrototypeOf` method
// https://tc39.es/ecma262/#sec-object.getprototypeof
// eslint-disable-next-line es/no-object-getprototypeof -- safe
var objectGetPrototypeOf = CORRECT_PROTOTYPE_GETTER ? $Object$1.getPrototypeOf : function (O) {
  var object = toObject$2(O);
  if (hasOwn$3(object, IE_PROTO)) return object[IE_PROTO];
  var constructor = object.constructor;
  if (isCallable$5(constructor) && object instanceof constructor) {
    return constructor.prototype;
  } return object instanceof $Object$1 ? ObjectPrototype : null;
};

var createNonEnumerableProperty$1 = createNonEnumerableProperty$4;

var defineBuiltIn$5 = function (target, key, value, options) {
  if (options && options.enumerable) target[key] = value;
  else createNonEnumerableProperty$1(target, key, value);
  return target;
};

var fails$4 = fails$d;
var isCallable$4 = isCallable$e;
var isObject$1 = isObject$7;
var create$2 = objectCreate;
var getPrototypeOf$1 = objectGetPrototypeOf;
var defineBuiltIn$4 = defineBuiltIn$5;
var wellKnownSymbol$8 = wellKnownSymbol$a;

var ITERATOR$5 = wellKnownSymbol$8('iterator');
var BUGGY_SAFARI_ITERATORS$1 = false;

// `%IteratorPrototype%` object
// https://tc39.es/ecma262/#sec-%iteratorprototype%-object
var IteratorPrototype$1, PrototypeOfArrayIteratorPrototype, arrayIterator;

/* eslint-disable es/no-array-prototype-keys -- safe */
if ([].keys) {
  arrayIterator = [].keys();
  // Safari 8 has buggy iterators w/o `next`
  if (!('next' in arrayIterator)) BUGGY_SAFARI_ITERATORS$1 = true;
  else {
    PrototypeOfArrayIteratorPrototype = getPrototypeOf$1(getPrototypeOf$1(arrayIterator));
    if (PrototypeOfArrayIteratorPrototype !== Object.prototype) IteratorPrototype$1 = PrototypeOfArrayIteratorPrototype;
  }
}

var NEW_ITERATOR_PROTOTYPE = !isObject$1(IteratorPrototype$1) || fails$4(function () {
  var test = {};
  // FF44- legacy iterators case
  return IteratorPrototype$1[ITERATOR$5].call(test) !== test;
});

if (NEW_ITERATOR_PROTOTYPE) IteratorPrototype$1 = {};
else IteratorPrototype$1 = create$2(IteratorPrototype$1);

// `%IteratorPrototype%[@@iterator]()` method
// https://tc39.es/ecma262/#sec-%iteratorprototype%-@@iterator
if (!isCallable$4(IteratorPrototype$1[ITERATOR$5])) {
  defineBuiltIn$4(IteratorPrototype$1, ITERATOR$5, function () {
    return this;
  });
}

var iteratorsCore = {
  IteratorPrototype: IteratorPrototype$1,
  BUGGY_SAFARI_ITERATORS: BUGGY_SAFARI_ITERATORS$1
};

var wellKnownSymbol$7 = wellKnownSymbol$a;

var TO_STRING_TAG$2 = wellKnownSymbol$7('toStringTag');
var test = {};

test[TO_STRING_TAG$2] = 'z';

var toStringTagSupport = String(test) === '[object z]';

var TO_STRING_TAG_SUPPORT$2 = toStringTagSupport;
var isCallable$3 = isCallable$e;
var classofRaw = classofRaw$2;
var wellKnownSymbol$6 = wellKnownSymbol$a;

var TO_STRING_TAG$1 = wellKnownSymbol$6('toStringTag');
var $Object = Object;

// ES3 wrong here
var CORRECT_ARGUMENTS = classofRaw(function () { return arguments; }()) === 'Arguments';

// fallback for IE11 Script Access Denied error
var tryGet = function (it, key) {
  try {
    return it[key];
  } catch (error) { /* empty */ }
};

// getting tag from ES6+ `Object.prototype.toString`
var classof$5 = TO_STRING_TAG_SUPPORT$2 ? classofRaw : function (it) {
  var O, tag, result;
  return it === undefined ? 'Undefined' : it === null ? 'Null'
    // @@toStringTag case
    : typeof (tag = tryGet(O = $Object(it), TO_STRING_TAG$1)) == 'string' ? tag
    // builtinTag case
    : CORRECT_ARGUMENTS ? classofRaw(O)
    // ES3 arguments fallback
    : (result = classofRaw(O)) === 'Object' && isCallable$3(O.callee) ? 'Arguments' : result;
};

var TO_STRING_TAG_SUPPORT$1 = toStringTagSupport;
var classof$4 = classof$5;

// `Object.prototype.toString` method implementation
// https://tc39.es/ecma262/#sec-object.prototype.tostring
var objectToString = TO_STRING_TAG_SUPPORT$1 ? {}.toString : function toString() {
  return '[object ' + classof$4(this) + ']';
};

var TO_STRING_TAG_SUPPORT = toStringTagSupport;
var defineProperty$2 = objectDefineProperty.f;
var createNonEnumerableProperty = createNonEnumerableProperty$4;
var hasOwn$2 = hasOwnProperty_1;
var toString$5 = objectToString;
var wellKnownSymbol$5 = wellKnownSymbol$a;

var TO_STRING_TAG = wellKnownSymbol$5('toStringTag');

var setToStringTag$5 = function (it, TAG, STATIC, SET_METHOD) {
  var target = STATIC ? it : it && it.prototype;
  if (target) {
    if (!hasOwn$2(target, TO_STRING_TAG)) {
      defineProperty$2(target, TO_STRING_TAG, { configurable: true, value: TAG });
    }
    if (SET_METHOD && !TO_STRING_TAG_SUPPORT) {
      createNonEnumerableProperty(target, 'toString', toString$5);
    }
  }
};

var IteratorPrototype = iteratorsCore.IteratorPrototype;
var create$1 = objectCreate;
var createPropertyDescriptor$2 = createPropertyDescriptor$5;
var setToStringTag$4 = setToStringTag$5;
var Iterators$5 = iterators;

var returnThis$1 = function () { return this; };

var iteratorCreateConstructor = function (IteratorConstructor, NAME, next, ENUMERABLE_NEXT) {
  var TO_STRING_TAG = NAME + ' Iterator';
  IteratorConstructor.prototype = create$1(IteratorPrototype, { next: createPropertyDescriptor$2(+!ENUMERABLE_NEXT, next) });
  setToStringTag$4(IteratorConstructor, TO_STRING_TAG, false, true);
  Iterators$5[TO_STRING_TAG] = returnThis$1;
  return IteratorConstructor;
};

var $$5 = _export;
var call$5 = functionCall;
var FunctionName = functionName;
var createIteratorConstructor$1 = iteratorCreateConstructor;
var getPrototypeOf = objectGetPrototypeOf;
var setToStringTag$3 = setToStringTag$5;
var defineBuiltIn$3 = defineBuiltIn$5;
var wellKnownSymbol$4 = wellKnownSymbol$a;
var Iterators$4 = iterators;
var IteratorsCore = iteratorsCore;

var PROPER_FUNCTION_NAME = FunctionName.PROPER;
FunctionName.CONFIGURABLE;
IteratorsCore.IteratorPrototype;
var BUGGY_SAFARI_ITERATORS = IteratorsCore.BUGGY_SAFARI_ITERATORS;
var ITERATOR$4 = wellKnownSymbol$4('iterator');
var KEYS = 'keys';
var VALUES = 'values';
var ENTRIES = 'entries';

var returnThis = function () { return this; };

var iteratorDefine = function (Iterable, NAME, IteratorConstructor, next, DEFAULT, IS_SET, FORCED) {
  createIteratorConstructor$1(IteratorConstructor, NAME, next);

  var getIterationMethod = function (KIND) {
    if (KIND === DEFAULT && defaultIterator) return defaultIterator;
    if (!BUGGY_SAFARI_ITERATORS && KIND && KIND in IterablePrototype) return IterablePrototype[KIND];

    switch (KIND) {
      case KEYS: return function keys() { return new IteratorConstructor(this, KIND); };
      case VALUES: return function values() { return new IteratorConstructor(this, KIND); };
      case ENTRIES: return function entries() { return new IteratorConstructor(this, KIND); };
    }

    return function () { return new IteratorConstructor(this); };
  };

  var TO_STRING_TAG = NAME + ' Iterator';
  var INCORRECT_VALUES_NAME = false;
  var IterablePrototype = Iterable.prototype;
  var nativeIterator = IterablePrototype[ITERATOR$4]
    || IterablePrototype['@@iterator']
    || DEFAULT && IterablePrototype[DEFAULT];
  var defaultIterator = !BUGGY_SAFARI_ITERATORS && nativeIterator || getIterationMethod(DEFAULT);
  var anyNativeIterator = NAME === 'Array' ? IterablePrototype.entries || nativeIterator : nativeIterator;
  var CurrentIteratorPrototype, methods, KEY;

  // fix native
  if (anyNativeIterator) {
    CurrentIteratorPrototype = getPrototypeOf(anyNativeIterator.call(new Iterable()));
    if (CurrentIteratorPrototype !== Object.prototype && CurrentIteratorPrototype.next) {
      // Set @@toStringTag to native iterators
      setToStringTag$3(CurrentIteratorPrototype, TO_STRING_TAG, true, true);
      Iterators$4[TO_STRING_TAG] = returnThis;
    }
  }

  // fix Array.prototype.{ values, @@iterator }.name in V8 / FF
  if (PROPER_FUNCTION_NAME && DEFAULT === VALUES && nativeIterator && nativeIterator.name !== VALUES) {
    {
      INCORRECT_VALUES_NAME = true;
      defaultIterator = function values() { return call$5(nativeIterator, this); };
    }
  }

  // export additional methods
  if (DEFAULT) {
    methods = {
      values: getIterationMethod(VALUES),
      keys: IS_SET ? defaultIterator : getIterationMethod(KEYS),
      entries: getIterationMethod(ENTRIES)
    };
    if (FORCED) for (KEY in methods) {
      if (BUGGY_SAFARI_ITERATORS || INCORRECT_VALUES_NAME || !(KEY in IterablePrototype)) {
        defineBuiltIn$3(IterablePrototype, KEY, methods[KEY]);
      }
    } else $$5({ target: NAME, proto: true, forced: BUGGY_SAFARI_ITERATORS || INCORRECT_VALUES_NAME }, methods);
  }

  // define iterator
  if ((FORCED) && IterablePrototype[ITERATOR$4] !== defaultIterator) {
    defineBuiltIn$3(IterablePrototype, ITERATOR$4, defaultIterator, { });
  }
  Iterators$4[NAME] = defaultIterator;

  return methods;
};

// `CreateIterResultObject` abstract operation
// https://tc39.es/ecma262/#sec-createiterresultobject
var createIterResultObject$3 = function (value, done) {
  return { value: value, done: done };
};

var toIndexedObject = toIndexedObject$5;
var Iterators$3 = iterators;
var InternalStateModule$3 = internalState;
objectDefineProperty.f;
var defineIterator$1 = iteratorDefine;
var createIterResultObject$2 = createIterResultObject$3;

var ARRAY_ITERATOR = 'Array Iterator';
var setInternalState$3 = InternalStateModule$3.set;
var getInternalState$1 = InternalStateModule$3.getterFor(ARRAY_ITERATOR);

// `Array.prototype.entries` method
// https://tc39.es/ecma262/#sec-array.prototype.entries
// `Array.prototype.keys` method
// https://tc39.es/ecma262/#sec-array.prototype.keys
// `Array.prototype.values` method
// https://tc39.es/ecma262/#sec-array.prototype.values
// `Array.prototype[@@iterator]` method
// https://tc39.es/ecma262/#sec-array.prototype-@@iterator
// `CreateArrayIterator` internal method
// https://tc39.es/ecma262/#sec-createarrayiterator
defineIterator$1(Array, 'Array', function (iterated, kind) {
  setInternalState$3(this, {
    type: ARRAY_ITERATOR,
    target: toIndexedObject(iterated), // target
    index: 0,                          // next index
    kind: kind                         // kind
  });
// `%ArrayIteratorPrototype%.next` method
// https://tc39.es/ecma262/#sec-%arrayiteratorprototype%.next
}, function () {
  var state = getInternalState$1(this);
  var target = state.target;
  var index = state.index++;
  if (!target || index >= target.length) {
    state.target = null;
    return createIterResultObject$2(undefined, true);
  }
  switch (state.kind) {
    case 'keys': return createIterResultObject$2(index, false);
    case 'values': return createIterResultObject$2(target[index], false);
  } return createIterResultObject$2([index, target[index]], false);
}, 'values');

// argumentsList[@@iterator] is %ArrayProto_values%
// https://tc39.es/ecma262/#sec-createunmappedargumentsobject
// https://tc39.es/ecma262/#sec-createmappedargumentsobject
Iterators$3.Arguments = Iterators$3.Array;

var $$4 = _export;
var uncurryThis$8 = functionUncurryThis;
var toAbsoluteIndex = toAbsoluteIndex$2;

var $RangeError$1 = RangeError;
var fromCharCode$2 = String.fromCharCode;
// eslint-disable-next-line es/no-string-fromcodepoint -- required for testing
var $fromCodePoint = String.fromCodePoint;
var join$3 = uncurryThis$8([].join);

// length should be 1, old FF problem
var INCORRECT_LENGTH = !!$fromCodePoint && $fromCodePoint.length !== 1;

// `String.fromCodePoint` method
// https://tc39.es/ecma262/#sec-string.fromcodepoint
$$4({ target: 'String', stat: true, forced: INCORRECT_LENGTH }, {
  // eslint-disable-next-line no-unused-vars -- required for `.length`
  fromCodePoint: function fromCodePoint(x) {
    var elements = [];
    var length = arguments.length;
    var i = 0;
    var code;
    while (length > i) {
      code = +arguments[i++];
      if (toAbsoluteIndex(code, 0x10FFFF) !== code) throw new $RangeError$1(code + ' is not a valid code point');
      elements[i] = code < 0x10000
        ? fromCharCode$2(code)
        : fromCharCode$2(((code -= 0x10000) >> 10) + 0xD800, code % 0x400 + 0xDC00);
    } return join$3(elements, '');
  }
});

var globalThis$4 = globalThis_1;
var DESCRIPTORS$5 = descriptors;

// eslint-disable-next-line es/no-object-getownpropertydescriptor -- safe
var getOwnPropertyDescriptor = Object.getOwnPropertyDescriptor;

// Avoid NodeJS experimental warning
var safeGetBuiltIn$1 = function (name) {
  if (!DESCRIPTORS$5) return globalThis$4[name];
  var descriptor = getOwnPropertyDescriptor(globalThis$4, name);
  return descriptor && descriptor.value;
};

var fails$3 = fails$d;
var wellKnownSymbol$3 = wellKnownSymbol$a;
var DESCRIPTORS$4 = descriptors;
var IS_PURE = isPure;

var ITERATOR$3 = wellKnownSymbol$3('iterator');

var urlConstructorDetection = !fails$3(function () {
  // eslint-disable-next-line unicorn/relative-url-style -- required for testing
  var url = new URL('b?a=1&b=2&c=3', 'https://a');
  var params = url.searchParams;
  var params2 = new URLSearchParams('a=1&a=2&b=3');
  var result = '';
  url.pathname = 'c%20d';
  params.forEach(function (value, key) {
    params['delete']('b');
    result += key + value;
  });
  params2['delete']('a', 2);
  // `undefined` case is a Chromium 117 bug
  // https://bugs.chromium.org/p/v8/issues/detail?id=14222
  params2['delete']('b', undefined);
  return (IS_PURE && (!url.toJSON || !params2.has('a', 1) || params2.has('a', 2) || !params2.has('a', undefined) || params2.has('b')))
    || (!params.size && (IS_PURE || !DESCRIPTORS$4))
    || !params.sort
    || url.href !== 'https://a/c%20d?a=1&c=3'
    || params.get('c') !== '3'
    || String(new URLSearchParams('?a=1')) !== 'a=1'
    || !params[ITERATOR$3]
    // throws in Edge
    || new URL('https://a@b').username !== 'a'
    || new URLSearchParams(new URLSearchParams('a=b')).get('a') !== 'b'
    // not punycoded in Edge
    || new URL('https://тест').host !== 'xn--e1aybc'
    // not escaped in Chrome 62-
    || new URL('https://a#б').hash !== '#%D0%B1'
    // fails in Chrome 66-
    || result !== 'a1c3'
    // throws in Safari
    || new URL('https://x', undefined).host !== 'x';
});

var defineProperty$1 = objectDefineProperty;

var defineBuiltInAccessor$2 = function (target, name, descriptor) {
  return defineProperty$1.f(target, name, descriptor);
};

var defineBuiltIn$2 = defineBuiltIn$5;

var defineBuiltIns$1 = function (target, src, options) {
  for (var key in src) {
    if (options && options.unsafe && target[key]) target[key] = src[key];
    else defineBuiltIn$2(target, key, src[key], options);
  } return target;
};

var isPrototypeOf = objectIsPrototypeOf;

var $TypeError$2 = TypeError;

var anInstance$2 = function (it, Prototype) {
  if (isPrototypeOf(Prototype, it)) return it;
  throw new $TypeError$2('Incorrect invocation');
};

var classof$3 = classof$5;

var $String = String;

var toString$4 = function (argument) {
  if (classof$3(argument) === 'Symbol') throw new TypeError('Cannot convert a Symbol value to a string');
  return $String(argument);
};

var classof$2 = classof$5;
var getMethod$1 = getMethod$3;
var isNullOrUndefined = isNullOrUndefined$3;
var Iterators$2 = iterators;
var wellKnownSymbol$2 = wellKnownSymbol$a;

var ITERATOR$2 = wellKnownSymbol$2('iterator');

var getIteratorMethod$3 = function (it) {
  if (!isNullOrUndefined(it)) return getMethod$1(it, ITERATOR$2)
    || getMethod$1(it, '@@iterator')
    || Iterators$2[classof$2(it)];
};

var call$4 = functionCall;
var aCallable = aCallable$3;
var anObject$3 = anObject$7;
var tryToString = tryToString$2;
var getIteratorMethod$2 = getIteratorMethod$3;

var $TypeError$1 = TypeError;

var getIterator$2 = function (argument, usingIterator) {
  var iteratorMethod = arguments.length < 2 ? getIteratorMethod$2(argument) : usingIterator;
  if (aCallable(iteratorMethod)) return anObject$3(call$4(iteratorMethod, argument));
  throw new $TypeError$1(tryToString(argument) + ' is not iterable');
};

var $TypeError = TypeError;

var validateArgumentsLength$4 = function (passed, required) {
  if (passed < required) throw new $TypeError('Not enough arguments');
  return passed;
};

var uncurryThis$7 = functionUncurryThis;

var arraySlice$2 = uncurryThis$7([].slice);

var arraySlice$1 = arraySlice$2;

var floor$2 = Math.floor;

var sort = function (array, comparefn) {
  var length = array.length;

  if (length < 8) {
    // insertion sort
    var i = 1;
    var element, j;

    while (i < length) {
      j = i;
      element = array[i];
      while (j && comparefn(array[j - 1], element) > 0) {
        array[j] = array[--j];
      }
      if (j !== i++) array[j] = element;
    }
  } else {
    // merge sort
    var middle = floor$2(length / 2);
    var left = sort(arraySlice$1(array, 0, middle), comparefn);
    var right = sort(arraySlice$1(array, middle), comparefn);
    var llength = left.length;
    var rlength = right.length;
    var lindex = 0;
    var rindex = 0;

    while (lindex < llength || rindex < rlength) {
      array[lindex + rindex] = (lindex < llength && rindex < rlength)
        ? comparefn(left[lindex], right[rindex]) <= 0 ? left[lindex++] : right[rindex++]
        : lindex < llength ? left[lindex++] : right[rindex++];
    }
  }

  return array;
};

var arraySort$1 = sort;

// TODO: in core-js@4, move /modules/ dependencies to public entries for better optimization by tools like `preset-env`


var $$3 = _export;
var globalThis$3 = globalThis_1;
var safeGetBuiltIn = safeGetBuiltIn$1;
var getBuiltIn$3 = getBuiltIn$6;
var call$3 = functionCall;
var uncurryThis$6 = functionUncurryThis;
var DESCRIPTORS$3 = descriptors;
var USE_NATIVE_URL$3 = urlConstructorDetection;
var defineBuiltIn$1 = defineBuiltIn$5;
var defineBuiltInAccessor$1 = defineBuiltInAccessor$2;
var defineBuiltIns = defineBuiltIns$1;
var setToStringTag$2 = setToStringTag$5;
var createIteratorConstructor = iteratorCreateConstructor;
var InternalStateModule$2 = internalState;
var anInstance$1 = anInstance$2;
var isCallable$2 = isCallable$e;
var hasOwn$1 = hasOwnProperty_1;
var bind$2 = functionBindContext;
var classof$1 = classof$5;
var anObject$2 = anObject$7;
var isObject = isObject$7;
var $toString$1 = toString$4;
var create = objectCreate;
var createPropertyDescriptor$1 = createPropertyDescriptor$5;
var getIterator$1 = getIterator$2;
var getIteratorMethod$1 = getIteratorMethod$3;
var createIterResultObject$1 = createIterResultObject$3;
var validateArgumentsLength$3 = validateArgumentsLength$4;
var wellKnownSymbol$1 = wellKnownSymbol$a;
var arraySort = arraySort$1;

var ITERATOR$1 = wellKnownSymbol$1('iterator');
var URL_SEARCH_PARAMS = 'URLSearchParams';
var URL_SEARCH_PARAMS_ITERATOR = URL_SEARCH_PARAMS + 'Iterator';
var setInternalState$2 = InternalStateModule$2.set;
var getInternalParamsState = InternalStateModule$2.getterFor(URL_SEARCH_PARAMS);
var getInternalIteratorState = InternalStateModule$2.getterFor(URL_SEARCH_PARAMS_ITERATOR);

var nativeFetch = safeGetBuiltIn('fetch');
var NativeRequest = safeGetBuiltIn('Request');
var Headers = safeGetBuiltIn('Headers');
var RequestPrototype = NativeRequest && NativeRequest.prototype;
var HeadersPrototype = Headers && Headers.prototype;
var TypeError$2 = globalThis$3.TypeError;
var encodeURIComponent$1 = globalThis$3.encodeURIComponent;
var fromCharCode$1 = String.fromCharCode;
var fromCodePoint = getBuiltIn$3('String', 'fromCodePoint');
var $parseInt = parseInt;
var charAt$3 = uncurryThis$6(''.charAt);
var join$2 = uncurryThis$6([].join);
var push$2 = uncurryThis$6([].push);
var replace$2 = uncurryThis$6(''.replace);
var shift$1 = uncurryThis$6([].shift);
var splice = uncurryThis$6([].splice);
var split$2 = uncurryThis$6(''.split);
var stringSlice$2 = uncurryThis$6(''.slice);
var exec$3 = uncurryThis$6(/./.exec);

var plus = /\+/g;
var FALLBACK_REPLACER = '\uFFFD';
var VALID_HEX = /^[0-9a-f]+$/i;

var parseHexOctet = function (string, start) {
  var substr = stringSlice$2(string, start, start + 2);
  if (!exec$3(VALID_HEX, substr)) return NaN;

  return $parseInt(substr, 16);
};

var getLeadingOnes = function (octet) {
  var count = 0;
  for (var mask = 0x80; mask > 0 && (octet & mask) !== 0; mask >>= 1) {
    count++;
  }
  return count;
};

var utf8Decode = function (octets) {
  var codePoint = null;

  switch (octets.length) {
    case 1:
      codePoint = octets[0];
      break;
    case 2:
      codePoint = (octets[0] & 0x1F) << 6 | (octets[1] & 0x3F);
      break;
    case 3:
      codePoint = (octets[0] & 0x0F) << 12 | (octets[1] & 0x3F) << 6 | (octets[2] & 0x3F);
      break;
    case 4:
      codePoint = (octets[0] & 0x07) << 18 | (octets[1] & 0x3F) << 12 | (octets[2] & 0x3F) << 6 | (octets[3] & 0x3F);
      break;
  }

  return codePoint > 0x10FFFF ? null : codePoint;
};

var decode$1 = function (input) {
  input = replace$2(input, plus, ' ');
  var length = input.length;
  var result = '';
  var i = 0;

  while (i < length) {
    var decodedChar = charAt$3(input, i);

    if (decodedChar === '%') {
      if (charAt$3(input, i + 1) === '%' || i + 3 > length) {
        result += '%';
        i++;
        continue;
      }

      var octet = parseHexOctet(input, i + 1);

      // eslint-disable-next-line no-self-compare -- NaN check
      if (octet !== octet) {
        result += decodedChar;
        i++;
        continue;
      }

      i += 2;
      var byteSequenceLength = getLeadingOnes(octet);

      if (byteSequenceLength === 0) {
        decodedChar = fromCharCode$1(octet);
      } else {
        if (byteSequenceLength === 1 || byteSequenceLength > 4) {
          result += FALLBACK_REPLACER;
          i++;
          continue;
        }

        var octets = [octet];
        var sequenceIndex = 1;

        while (sequenceIndex < byteSequenceLength) {
          i++;
          if (i + 3 > length || charAt$3(input, i) !== '%') break;

          var nextByte = parseHexOctet(input, i + 1);

          // eslint-disable-next-line no-self-compare -- NaN check
          if (nextByte !== nextByte) {
            i += 3;
            break;
          }
          if (nextByte > 191 || nextByte < 128) break;

          push$2(octets, nextByte);
          i += 2;
          sequenceIndex++;
        }

        if (octets.length !== byteSequenceLength) {
          result += FALLBACK_REPLACER;
          continue;
        }

        var codePoint = utf8Decode(octets);
        if (codePoint === null) {
          result += FALLBACK_REPLACER;
        } else {
          decodedChar = fromCodePoint(codePoint);
        }
      }
    }

    result += decodedChar;
    i++;
  }

  return result;
};

var find = /[!'()~]|%20/g;

var replacements = {
  '!': '%21',
  "'": '%27',
  '(': '%28',
  ')': '%29',
  '~': '%7E',
  '%20': '+'
};

var replacer = function (match) {
  return replacements[match];
};

var serialize = function (it) {
  return replace$2(encodeURIComponent$1(it), find, replacer);
};

var URLSearchParamsIterator = createIteratorConstructor(function Iterator(params, kind) {
  setInternalState$2(this, {
    type: URL_SEARCH_PARAMS_ITERATOR,
    target: getInternalParamsState(params).entries,
    index: 0,
    kind: kind
  });
}, URL_SEARCH_PARAMS, function next() {
  var state = getInternalIteratorState(this);
  var target = state.target;
  var index = state.index++;
  if (!target || index >= target.length) {
    state.target = null;
    return createIterResultObject$1(undefined, true);
  }
  var entry = target[index];
  switch (state.kind) {
    case 'keys': return createIterResultObject$1(entry.key, false);
    case 'values': return createIterResultObject$1(entry.value, false);
  } return createIterResultObject$1([entry.key, entry.value], false);
}, true);

var URLSearchParamsState = function (init) {
  this.entries = [];
  this.url = null;

  if (init !== undefined) {
    if (isObject(init)) this.parseObject(init);
    else this.parseQuery(typeof init == 'string' ? charAt$3(init, 0) === '?' ? stringSlice$2(init, 1) : init : $toString$1(init));
  }
};

URLSearchParamsState.prototype = {
  type: URL_SEARCH_PARAMS,
  bindURL: function (url) {
    this.url = url;
    this.update();
  },
  parseObject: function (object) {
    var entries = this.entries;
    var iteratorMethod = getIteratorMethod$1(object);
    var iterator, next, step, entryIterator, entryNext, first, second;

    if (iteratorMethod) {
      iterator = getIterator$1(object, iteratorMethod);
      next = iterator.next;
      while (!(step = call$3(next, iterator)).done) {
        entryIterator = getIterator$1(anObject$2(step.value));
        entryNext = entryIterator.next;
        if (
          (first = call$3(entryNext, entryIterator)).done ||
          (second = call$3(entryNext, entryIterator)).done ||
          !call$3(entryNext, entryIterator).done
        ) throw new TypeError$2('Expected sequence with length 2');
        push$2(entries, { key: $toString$1(first.value), value: $toString$1(second.value) });
      }
    } else for (var key in object) if (hasOwn$1(object, key)) {
      push$2(entries, { key: key, value: $toString$1(object[key]) });
    }
  },
  parseQuery: function (query) {
    if (query) {
      var entries = this.entries;
      var attributes = split$2(query, '&');
      var index = 0;
      var attribute, entry;
      while (index < attributes.length) {
        attribute = attributes[index++];
        if (attribute.length) {
          entry = split$2(attribute, '=');
          push$2(entries, {
            key: decode$1(shift$1(entry)),
            value: decode$1(join$2(entry, '='))
          });
        }
      }
    }
  },
  serialize: function () {
    var entries = this.entries;
    var result = [];
    var index = 0;
    var entry;
    while (index < entries.length) {
      entry = entries[index++];
      push$2(result, serialize(entry.key) + '=' + serialize(entry.value));
    } return join$2(result, '&');
  },
  update: function () {
    this.entries.length = 0;
    this.parseQuery(this.url.query);
  },
  updateURL: function () {
    if (this.url) this.url.update();
  }
};

// `URLSearchParams` constructor
// https://url.spec.whatwg.org/#interface-urlsearchparams
var URLSearchParamsConstructor = function URLSearchParams(/* init */) {
  anInstance$1(this, URLSearchParamsPrototype);
  var init = arguments.length > 0 ? arguments[0] : undefined;
  var state = setInternalState$2(this, new URLSearchParamsState(init));
  if (!DESCRIPTORS$3) this.size = state.entries.length;
};

var URLSearchParamsPrototype = URLSearchParamsConstructor.prototype;

defineBuiltIns(URLSearchParamsPrototype, {
  // `URLSearchParams.prototype.append` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-append
  append: function append(name, value) {
    var state = getInternalParamsState(this);
    validateArgumentsLength$3(arguments.length, 2);
    push$2(state.entries, { key: $toString$1(name), value: $toString$1(value) });
    if (!DESCRIPTORS$3) this.length++;
    state.updateURL();
  },
  // `URLSearchParams.prototype.delete` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-delete
  'delete': function (name /* , value */) {
    var state = getInternalParamsState(this);
    var length = validateArgumentsLength$3(arguments.length, 1);
    var entries = state.entries;
    var key = $toString$1(name);
    var $value = length < 2 ? undefined : arguments[1];
    var value = $value === undefined ? $value : $toString$1($value);
    var index = 0;
    while (index < entries.length) {
      var entry = entries[index];
      if (entry.key === key && (value === undefined || entry.value === value)) {
        splice(entries, index, 1);
        if (value !== undefined) break;
      } else index++;
    }
    if (!DESCRIPTORS$3) this.size = entries.length;
    state.updateURL();
  },
  // `URLSearchParams.prototype.get` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-get
  get: function get(name) {
    var entries = getInternalParamsState(this).entries;
    validateArgumentsLength$3(arguments.length, 1);
    var key = $toString$1(name);
    var index = 0;
    for (; index < entries.length; index++) {
      if (entries[index].key === key) return entries[index].value;
    }
    return null;
  },
  // `URLSearchParams.prototype.getAll` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-getall
  getAll: function getAll(name) {
    var entries = getInternalParamsState(this).entries;
    validateArgumentsLength$3(arguments.length, 1);
    var key = $toString$1(name);
    var result = [];
    var index = 0;
    for (; index < entries.length; index++) {
      if (entries[index].key === key) push$2(result, entries[index].value);
    }
    return result;
  },
  // `URLSearchParams.prototype.has` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-has
  has: function has(name /* , value */) {
    var entries = getInternalParamsState(this).entries;
    var length = validateArgumentsLength$3(arguments.length, 1);
    var key = $toString$1(name);
    var $value = length < 2 ? undefined : arguments[1];
    var value = $value === undefined ? $value : $toString$1($value);
    var index = 0;
    while (index < entries.length) {
      var entry = entries[index++];
      if (entry.key === key && (value === undefined || entry.value === value)) return true;
    }
    return false;
  },
  // `URLSearchParams.prototype.set` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-set
  set: function set(name, value) {
    var state = getInternalParamsState(this);
    validateArgumentsLength$3(arguments.length, 1);
    var entries = state.entries;
    var found = false;
    var key = $toString$1(name);
    var val = $toString$1(value);
    var index = 0;
    var entry;
    for (; index < entries.length; index++) {
      entry = entries[index];
      if (entry.key === key) {
        if (found) splice(entries, index--, 1);
        else {
          found = true;
          entry.value = val;
        }
      }
    }
    if (!found) push$2(entries, { key: key, value: val });
    if (!DESCRIPTORS$3) this.size = entries.length;
    state.updateURL();
  },
  // `URLSearchParams.prototype.sort` method
  // https://url.spec.whatwg.org/#dom-urlsearchparams-sort
  sort: function sort() {
    var state = getInternalParamsState(this);
    arraySort(state.entries, function (a, b) {
      return a.key > b.key ? 1 : -1;
    });
    state.updateURL();
  },
  // `URLSearchParams.prototype.forEach` method
  forEach: function forEach(callback /* , thisArg */) {
    var entries = getInternalParamsState(this).entries;
    var boundFunction = bind$2(callback, arguments.length > 1 ? arguments[1] : undefined);
    var index = 0;
    var entry;
    while (index < entries.length) {
      entry = entries[index++];
      boundFunction(entry.value, entry.key, this);
    }
  },
  // `URLSearchParams.prototype.keys` method
  keys: function keys() {
    return new URLSearchParamsIterator(this, 'keys');
  },
  // `URLSearchParams.prototype.values` method
  values: function values() {
    return new URLSearchParamsIterator(this, 'values');
  },
  // `URLSearchParams.prototype.entries` method
  entries: function entries() {
    return new URLSearchParamsIterator(this, 'entries');
  }
}, { enumerable: true });

// `URLSearchParams.prototype[@@iterator]` method
defineBuiltIn$1(URLSearchParamsPrototype, ITERATOR$1, URLSearchParamsPrototype.entries, { });

// `URLSearchParams.prototype.toString` method
// https://url.spec.whatwg.org/#urlsearchparams-stringification-behavior
defineBuiltIn$1(URLSearchParamsPrototype, 'toString', function toString() {
  return getInternalParamsState(this).serialize();
}, { enumerable: true });

// `URLSearchParams.prototype.size` getter
// https://github.com/whatwg/url/pull/734
if (DESCRIPTORS$3) defineBuiltInAccessor$1(URLSearchParamsPrototype, 'size', {
  get: function size() {
    return getInternalParamsState(this).entries.length;
  },
  configurable: true,
  enumerable: true
});

setToStringTag$2(URLSearchParamsConstructor, URL_SEARCH_PARAMS);

$$3({ global: true, forced: !USE_NATIVE_URL$3 }, {
  URLSearchParams: URLSearchParamsConstructor
});

// Wrap `fetch` and `Request` for correct work with polyfilled `URLSearchParams`
if (!USE_NATIVE_URL$3 && isCallable$2(Headers)) {
  var headersHas = uncurryThis$6(HeadersPrototype.has);
  var headersSet = uncurryThis$6(HeadersPrototype.set);

  var wrapRequestOptions = function (init) {
    if (isObject(init)) {
      var body = init.body;
      var headers;
      if (classof$1(body) === URL_SEARCH_PARAMS) {
        headers = init.headers ? new Headers(init.headers) : new Headers();
        if (!headersHas(headers, 'content-type')) {
          headersSet(headers, 'content-type', 'application/x-www-form-urlencoded;charset=UTF-8');
        }
        return create(init, {
          body: createPropertyDescriptor$1(0, $toString$1(body)),
          headers: createPropertyDescriptor$1(0, headers)
        });
      }
    } return init;
  };

  if (isCallable$2(nativeFetch)) {
    $$3({ global: true, dontCallGetSet: true, forced: true }, {
      fetch: function fetch(input /* , init */) {
        return nativeFetch(input, arguments.length > 1 ? wrapRequestOptions(arguments[1]) : {});
      }
    });
  }

  if (isCallable$2(NativeRequest)) {
    var RequestConstructor = function Request(input /* , init */) {
      anInstance$1(this, RequestPrototype);
      return new NativeRequest(input, arguments.length > 1 ? wrapRequestOptions(arguments[1]) : {});
    };

    RequestPrototype.constructor = RequestConstructor;
    RequestConstructor.prototype = RequestPrototype;

    $$3({ global: true, dontCallGetSet: true, forced: true }, {
      Request: RequestConstructor
    });
  }
}

var web_urlSearchParams_constructor = {
  URLSearchParams: URLSearchParamsConstructor,
  getState: getInternalParamsState
};

var path$1 = path$4;

var urlSearchParams$4 = path$1.URLSearchParams;

// iterable DOM collections
// flag - `iterable` interface - 'entries', 'keys', 'values', 'forEach' methods
var domIterables = {
  CSSRuleList: 0,
  CSSStyleDeclaration: 0,
  CSSValueList: 0,
  ClientRectList: 0,
  DOMRectList: 0,
  DOMStringList: 0,
  DOMTokenList: 1,
  DataTransferItemList: 0,
  FileList: 0,
  HTMLAllCollection: 0,
  HTMLCollection: 0,
  HTMLFormElement: 0,
  HTMLSelectElement: 0,
  MediaList: 0,
  MimeTypeArray: 0,
  NamedNodeMap: 0,
  NodeList: 1,
  PaintRequestList: 0,
  Plugin: 0,
  PluginArray: 0,
  SVGLengthList: 0,
  SVGNumberList: 0,
  SVGPathSegList: 0,
  SVGPointList: 0,
  SVGStringList: 0,
  SVGTransformList: 0,
  SourceBufferList: 0,
  StyleSheetList: 0,
  TextTrackCueList: 0,
  TextTrackList: 0,
  TouchList: 0
};

var DOMIterables = domIterables;
var globalThis$2 = globalThis_1;
var setToStringTag$1 = setToStringTag$5;
var Iterators$1 = iterators;

for (var COLLECTION_NAME in DOMIterables) {
  setToStringTag$1(globalThis$2[COLLECTION_NAME], COLLECTION_NAME);
  Iterators$1[COLLECTION_NAME] = Iterators$1.Array;
}

var parent$5 = urlSearchParams$4;


var urlSearchParams$3 = parent$5;

var parent$4 = urlSearchParams$3;

var urlSearchParams$2 = parent$4;

var parent$3 = urlSearchParams$2;

var urlSearchParams$1 = parent$3;

var urlSearchParams = urlSearchParams$1;

var URLSearchParams$2 = /*@__PURE__*/getDefaultExportFromCjs(urlSearchParams);

var uncurryThis$5 = functionUncurryThis;
var toIntegerOrInfinity = toIntegerOrInfinity$3;
var toString$3 = toString$4;
var requireObjectCoercible = requireObjectCoercible$3;

var charAt$2 = uncurryThis$5(''.charAt);
var charCodeAt$1 = uncurryThis$5(''.charCodeAt);
var stringSlice$1 = uncurryThis$5(''.slice);

var createMethod = function (CONVERT_TO_STRING) {
  return function ($this, pos) {
    var S = toString$3(requireObjectCoercible($this));
    var position = toIntegerOrInfinity(pos);
    var size = S.length;
    var first, second;
    if (position < 0 || position >= size) return CONVERT_TO_STRING ? '' : undefined;
    first = charCodeAt$1(S, position);
    return first < 0xD800 || first > 0xDBFF || position + 1 === size
      || (second = charCodeAt$1(S, position + 1)) < 0xDC00 || second > 0xDFFF
        ? CONVERT_TO_STRING
          ? charAt$2(S, position)
          : first
        : CONVERT_TO_STRING
          ? stringSlice$1(S, position, position + 2)
          : (first - 0xD800 << 10) + (second - 0xDC00) + 0x10000;
  };
};

var stringMultibyte = {
  // `String.prototype.codePointAt` method
  // https://tc39.es/ecma262/#sec-string.prototype.codepointat
  codeAt: createMethod(false),
  // `String.prototype.at` method
  // https://github.com/mathiasbynens/String.prototype.at
  charAt: createMethod(true)
};

var charAt$1 = stringMultibyte.charAt;
var toString$2 = toString$4;
var InternalStateModule$1 = internalState;
var defineIterator = iteratorDefine;
var createIterResultObject = createIterResultObject$3;

var STRING_ITERATOR = 'String Iterator';
var setInternalState$1 = InternalStateModule$1.set;
var getInternalState = InternalStateModule$1.getterFor(STRING_ITERATOR);

// `String.prototype[@@iterator]` method
// https://tc39.es/ecma262/#sec-string.prototype-@@iterator
defineIterator(String, 'String', function (iterated) {
  setInternalState$1(this, {
    type: STRING_ITERATOR,
    string: toString$2(iterated),
    index: 0
  });
// `%StringIteratorPrototype%.next` method
// https://tc39.es/ecma262/#sec-%stringiteratorprototype%.next
}, function next() {
  var state = getInternalState(this);
  var string = state.string;
  var index = state.index;
  var point;
  if (index >= string.length) return createIterResultObject(undefined, true);
  point = charAt$1(string, index);
  state.index += point.length;
  return createIterResultObject(point, false);
});

var objectGetOwnPropertySymbols = {};

// eslint-disable-next-line es/no-object-getownpropertysymbols -- safe
objectGetOwnPropertySymbols.f = Object.getOwnPropertySymbols;

var DESCRIPTORS$2 = descriptors;
var uncurryThis$4 = functionUncurryThis;
var call$2 = functionCall;
var fails$2 = fails$d;
var objectKeys = objectKeys$2;
var getOwnPropertySymbolsModule = objectGetOwnPropertySymbols;
var propertyIsEnumerableModule = objectPropertyIsEnumerable;
var toObject$1 = toObject$4;
var IndexedObject = indexedObject;

// eslint-disable-next-line es/no-object-assign -- safe
var $assign = Object.assign;
// eslint-disable-next-line es/no-object-defineproperty -- required for testing
var defineProperty = Object.defineProperty;
var concat = uncurryThis$4([].concat);

// `Object.assign` method
// https://tc39.es/ecma262/#sec-object.assign
var objectAssign = !$assign || fails$2(function () {
  // should have correct order of operations (Edge bug)
  if (DESCRIPTORS$2 && $assign({ b: 1 }, $assign(defineProperty({}, 'a', {
    enumerable: true,
    get: function () {
      defineProperty(this, 'b', {
        value: 3,
        enumerable: false
      });
    }
  }), { b: 2 })).b !== 1) return true;
  // should work with symbols and should have deterministic property order (V8 bug)
  var A = {};
  var B = {};
  // eslint-disable-next-line es/no-symbol -- safe
  var symbol = Symbol('assign detection');
  var alphabet = 'abcdefghijklmnopqrst';
  A[symbol] = 7;
  // eslint-disable-next-line es/no-array-prototype-foreach -- safe
  alphabet.split('').forEach(function (chr) { B[chr] = chr; });
  return $assign({}, A)[symbol] !== 7 || objectKeys($assign({}, B)).join('') !== alphabet;
}) ? function assign(target, source) { // eslint-disable-line no-unused-vars -- required for `.length`
  var T = toObject$1(target);
  var argumentsLength = arguments.length;
  var index = 1;
  var getOwnPropertySymbols = getOwnPropertySymbolsModule.f;
  var propertyIsEnumerable = propertyIsEnumerableModule.f;
  while (argumentsLength > index) {
    var S = IndexedObject(arguments[index++]);
    var keys = getOwnPropertySymbols ? concat(objectKeys(S), getOwnPropertySymbols(S)) : objectKeys(S);
    var length = keys.length;
    var j = 0;
    var key;
    while (length > j) {
      key = keys[j++];
      if (!DESCRIPTORS$2 || call$2(propertyIsEnumerable, S, key)) T[key] = S[key];
    }
  } return T;
} : $assign;

var call$1 = functionCall;
var anObject$1 = anObject$7;
var getMethod = getMethod$3;

var iteratorClose$1 = function (iterator, kind, value) {
  var innerResult, innerError;
  anObject$1(iterator);
  try {
    innerResult = getMethod(iterator, 'return');
    if (!innerResult) {
      if (kind === 'throw') throw value;
      return value;
    }
    innerResult = call$1(innerResult, iterator);
  } catch (error) {
    innerError = true;
    innerResult = error;
  }
  if (kind === 'throw') throw value;
  if (innerError) throw innerResult;
  anObject$1(innerResult);
  return value;
};

var anObject = anObject$7;
var iteratorClose = iteratorClose$1;

// call something on iterator step with safe closing on error
var callWithSafeIterationClosing$1 = function (iterator, fn, value, ENTRIES) {
  try {
    return ENTRIES ? fn(anObject(value)[0], value[1]) : fn(value);
  } catch (error) {
    iteratorClose(iterator, 'throw', error);
  }
};

var wellKnownSymbol = wellKnownSymbol$a;
var Iterators = iterators;

var ITERATOR = wellKnownSymbol('iterator');
var ArrayPrototype = Array.prototype;

// check on default Array iterator
var isArrayIteratorMethod$1 = function (it) {
  return it !== undefined && (Iterators.Array === it || ArrayPrototype[ITERATOR] === it);
};

var uncurryThis$3 = functionUncurryThis;
var isCallable$1 = isCallable$e;
var store = sharedStoreExports;

var functionToString = uncurryThis$3(Function.toString);

// this helper broken in `core-js@3.4.1-3.4.4`, so we can't use `shared` helper
if (!isCallable$1(store.inspectSource)) {
  store.inspectSource = function (it) {
    return functionToString(it);
  };
}

var inspectSource$1 = store.inspectSource;

var uncurryThis$2 = functionUncurryThis;
var fails$1 = fails$d;
var isCallable = isCallable$e;
var classof = classof$5;
var getBuiltIn$2 = getBuiltIn$6;
var inspectSource = inspectSource$1;

var noop = function () { /* empty */ };
var construct = getBuiltIn$2('Reflect', 'construct');
var constructorRegExp = /^\s*(?:class|function)\b/;
var exec$2 = uncurryThis$2(constructorRegExp.exec);
var INCORRECT_TO_STRING = !constructorRegExp.test(noop);

var isConstructorModern = function isConstructor(argument) {
  if (!isCallable(argument)) return false;
  try {
    construct(noop, [], argument);
    return true;
  } catch (error) {
    return false;
  }
};

var isConstructorLegacy = function isConstructor(argument) {
  if (!isCallable(argument)) return false;
  switch (classof(argument)) {
    case 'AsyncFunction':
    case 'GeneratorFunction':
    case 'AsyncGeneratorFunction': return false;
  }
  try {
    // we can't check .prototype since constructors produced by .bind haven't it
    // `Function#toString` throws on some built-it function in some legacy engines
    // (for example, `DOMQuad` and similar in FF41-)
    return INCORRECT_TO_STRING || !!exec$2(constructorRegExp, inspectSource(argument));
  } catch (error) {
    return true;
  }
};

isConstructorLegacy.sham = true;

// `IsConstructor` abstract operation
// https://tc39.es/ecma262/#sec-isconstructor
var isConstructor$1 = !construct || fails$1(function () {
  var called;
  return isConstructorModern(isConstructorModern.call)
    || !isConstructorModern(Object)
    || !isConstructorModern(function () { called = true; })
    || called;
}) ? isConstructorLegacy : isConstructorModern;

var DESCRIPTORS$1 = descriptors;
var definePropertyModule = objectDefineProperty;
var createPropertyDescriptor = createPropertyDescriptor$5;

var createProperty$1 = function (object, key, value) {
  if (DESCRIPTORS$1) definePropertyModule.f(object, key, createPropertyDescriptor(0, value));
  else object[key] = value;
};

var bind$1 = functionBindContext;
var call = functionCall;
var toObject = toObject$4;
var callWithSafeIterationClosing = callWithSafeIterationClosing$1;
var isArrayIteratorMethod = isArrayIteratorMethod$1;
var isConstructor = isConstructor$1;
var lengthOfArrayLike = lengthOfArrayLike$2;
var createProperty = createProperty$1;
var getIterator = getIterator$2;
var getIteratorMethod = getIteratorMethod$3;

var $Array = Array;

// `Array.from` method implementation
// https://tc39.es/ecma262/#sec-array.from
var arrayFrom$1 = function from(arrayLike /* , mapfn = undefined, thisArg = undefined */) {
  var O = toObject(arrayLike);
  var IS_CONSTRUCTOR = isConstructor(this);
  var argumentsLength = arguments.length;
  var mapfn = argumentsLength > 1 ? arguments[1] : undefined;
  var mapping = mapfn !== undefined;
  if (mapping) mapfn = bind$1(mapfn, argumentsLength > 2 ? arguments[2] : undefined);
  var iteratorMethod = getIteratorMethod(O);
  var index = 0;
  var length, result, step, iterator, next, value;
  // if the target is not iterable or it's an array with the default iterator - use a simple case
  if (iteratorMethod && !(this === $Array && isArrayIteratorMethod(iteratorMethod))) {
    result = IS_CONSTRUCTOR ? new this() : [];
    iterator = getIterator(O, iteratorMethod);
    next = iterator.next;
    for (;!(step = call(next, iterator)).done; index++) {
      value = mapping ? callWithSafeIterationClosing(iterator, mapfn, [step.value, index], true) : step.value;
      createProperty(result, index, value);
    }
  } else {
    length = lengthOfArrayLike(O);
    result = IS_CONSTRUCTOR ? new this(length) : $Array(length);
    for (;length > index; index++) {
      value = mapping ? mapfn(O[index], index) : O[index];
      createProperty(result, index, value);
    }
  }
  result.length = index;
  return result;
};

// based on https://github.com/bestiejs/punycode.js/blob/master/punycode.js
var uncurryThis$1 = functionUncurryThis;

var maxInt = 2147483647; // aka. 0x7FFFFFFF or 2^31-1
var base = 36;
var tMin = 1;
var tMax = 26;
var skew = 38;
var damp = 700;
var initialBias = 72;
var initialN = 128; // 0x80
var delimiter = '-'; // '\x2D'
var regexNonASCII = /[^\0-\u007E]/; // non-ASCII chars
var regexSeparators = /[.\u3002\uFF0E\uFF61]/g; // RFC 3490 separators
var OVERFLOW_ERROR = 'Overflow: input needs wider integers to process';
var baseMinusTMin = base - tMin;

var $RangeError = RangeError;
var exec$1 = uncurryThis$1(regexSeparators.exec);
var floor$1 = Math.floor;
var fromCharCode = String.fromCharCode;
var charCodeAt = uncurryThis$1(''.charCodeAt);
var join$1 = uncurryThis$1([].join);
var push$1 = uncurryThis$1([].push);
var replace$1 = uncurryThis$1(''.replace);
var split$1 = uncurryThis$1(''.split);
var toLowerCase$1 = uncurryThis$1(''.toLowerCase);

/**
 * Creates an array containing the numeric code points of each Unicode
 * character in the string. While JavaScript uses UCS-2 internally,
 * this function will convert a pair of surrogate halves (each of which
 * UCS-2 exposes as separate characters) into a single code point,
 * matching UTF-16.
 */
var ucs2decode = function (string) {
  var output = [];
  var counter = 0;
  var length = string.length;
  while (counter < length) {
    var value = charCodeAt(string, counter++);
    if (value >= 0xD800 && value <= 0xDBFF && counter < length) {
      // It's a high surrogate, and there is a next character.
      var extra = charCodeAt(string, counter++);
      if ((extra & 0xFC00) === 0xDC00) { // Low surrogate.
        push$1(output, ((value & 0x3FF) << 10) + (extra & 0x3FF) + 0x10000);
      } else {
        // It's an unmatched surrogate; only append this code unit, in case the
        // next code unit is the high surrogate of a surrogate pair.
        push$1(output, value);
        counter--;
      }
    } else {
      push$1(output, value);
    }
  }
  return output;
};

/**
 * Converts a digit/integer into a basic code point.
 */
var digitToBasic = function (digit) {
  //  0..25 map to ASCII a..z or A..Z
  // 26..35 map to ASCII 0..9
  return digit + 22 + 75 * (digit < 26);
};

/**
 * Bias adaptation function as per section 3.4 of RFC 3492.
 * https://tools.ietf.org/html/rfc3492#section-3.4
 */
var adapt = function (delta, numPoints, firstTime) {
  var k = 0;
  delta = firstTime ? floor$1(delta / damp) : delta >> 1;
  delta += floor$1(delta / numPoints);
  while (delta > baseMinusTMin * tMax >> 1) {
    delta = floor$1(delta / baseMinusTMin);
    k += base;
  }
  return floor$1(k + (baseMinusTMin + 1) * delta / (delta + skew));
};

/**
 * Converts a string of Unicode symbols (e.g. a domain name label) to a
 * Punycode string of ASCII-only symbols.
 */
var encode$1 = function (input) {
  var output = [];

  // Convert the input in UCS-2 to an array of Unicode code points.
  input = ucs2decode(input);

  // Cache the length.
  var inputLength = input.length;

  // Initialize the state.
  var n = initialN;
  var delta = 0;
  var bias = initialBias;
  var i, currentValue;

  // Handle the basic code points.
  for (i = 0; i < input.length; i++) {
    currentValue = input[i];
    if (currentValue < 0x80) {
      push$1(output, fromCharCode(currentValue));
    }
  }

  var basicLength = output.length; // number of basic code points.
  var handledCPCount = basicLength; // number of code points that have been handled;

  // Finish the basic string with a delimiter unless it's empty.
  if (basicLength) {
    push$1(output, delimiter);
  }

  // Main encoding loop:
  while (handledCPCount < inputLength) {
    // All non-basic code points < n have been handled already. Find the next larger one:
    var m = maxInt;
    for (i = 0; i < input.length; i++) {
      currentValue = input[i];
      if (currentValue >= n && currentValue < m) {
        m = currentValue;
      }
    }

    // Increase `delta` enough to advance the decoder's <n,i> state to <m,0>, but guard against overflow.
    var handledCPCountPlusOne = handledCPCount + 1;
    if (m - n > floor$1((maxInt - delta) / handledCPCountPlusOne)) {
      throw new $RangeError(OVERFLOW_ERROR);
    }

    delta += (m - n) * handledCPCountPlusOne;
    n = m;

    for (i = 0; i < input.length; i++) {
      currentValue = input[i];
      if (currentValue < n && ++delta > maxInt) {
        throw new $RangeError(OVERFLOW_ERROR);
      }
      if (currentValue === n) {
        // Represent delta as a generalized variable-length integer.
        var q = delta;
        var k = base;
        while (true) {
          var t = k <= bias ? tMin : k >= bias + tMax ? tMax : k - bias;
          if (q < t) break;
          var qMinusT = q - t;
          var baseMinusT = base - t;
          push$1(output, fromCharCode(digitToBasic(t + qMinusT % baseMinusT)));
          q = floor$1(qMinusT / baseMinusT);
          k += base;
        }

        push$1(output, fromCharCode(digitToBasic(q)));
        bias = adapt(delta, handledCPCountPlusOne, handledCPCount === basicLength);
        delta = 0;
        handledCPCount++;
      }
    }

    delta++;
    n++;
  }
  return join$1(output, '');
};

var stringPunycodeToAscii = function (input) {
  var encoded = [];
  var labels = split$1(replace$1(toLowerCase$1(input), regexSeparators, '\u002E'), '.');
  var i, label;
  for (i = 0; i < labels.length; i++) {
    label = labels[i];
    push$1(encoded, exec$1(regexNonASCII, label) ? 'xn--' + encode$1(label) : label);
  }
  return join$1(encoded, '.');
};

// TODO: in core-js@4, move /modules/ dependencies to public entries for better optimization by tools like `preset-env`

var $$2 = _export;
var DESCRIPTORS = descriptors;
var USE_NATIVE_URL$2 = urlConstructorDetection;
var globalThis$1 = globalThis_1;
var bind = functionBindContext;
var uncurryThis = functionUncurryThis;
var defineBuiltIn = defineBuiltIn$5;
var defineBuiltInAccessor = defineBuiltInAccessor$2;
var anInstance = anInstance$2;
var hasOwn = hasOwnProperty_1;
var assign = objectAssign;
var arrayFrom = arrayFrom$1;
var arraySlice = arraySlice$2;
var codeAt = stringMultibyte.codeAt;
var toASCII = stringPunycodeToAscii;
var $toString = toString$4;
var setToStringTag = setToStringTag$5;
var validateArgumentsLength$2 = validateArgumentsLength$4;
var URLSearchParamsModule = web_urlSearchParams_constructor;
var InternalStateModule = internalState;

var setInternalState = InternalStateModule.set;
var getInternalURLState = InternalStateModule.getterFor('URL');
var URLSearchParams$1 = URLSearchParamsModule.URLSearchParams;
var getInternalSearchParamsState = URLSearchParamsModule.getState;

var NativeURL = globalThis$1.URL;
var TypeError$1 = globalThis$1.TypeError;
var parseInt$1 = globalThis$1.parseInt;
var floor = Math.floor;
var pow = Math.pow;
var charAt = uncurryThis(''.charAt);
var exec = uncurryThis(/./.exec);
var join = uncurryThis([].join);
var numberToString = uncurryThis(1.1.toString);
var pop = uncurryThis([].pop);
var push = uncurryThis([].push);
var replace = uncurryThis(''.replace);
var shift = uncurryThis([].shift);
var split = uncurryThis(''.split);
var stringSlice = uncurryThis(''.slice);
var toLowerCase = uncurryThis(''.toLowerCase);
var unshift = uncurryThis([].unshift);

var INVALID_AUTHORITY = 'Invalid authority';
var INVALID_SCHEME = 'Invalid scheme';
var INVALID_HOST = 'Invalid host';
var INVALID_PORT = 'Invalid port';

var ALPHA = /[a-z]/i;
// eslint-disable-next-line regexp/no-obscure-range -- safe
var ALPHANUMERIC = /[\d+-.a-z]/i;
var DIGIT = /\d/;
var HEX_START = /^0x/i;
var OCT = /^[0-7]+$/;
var DEC = /^\d+$/;
var HEX = /^[\da-f]+$/i;
/* eslint-disable regexp/no-control-character -- safe */
var FORBIDDEN_HOST_CODE_POINT = /[\0\t\n\r #%/:<>?@[\\\]^|]/;
var FORBIDDEN_HOST_CODE_POINT_EXCLUDING_PERCENT = /[\0\t\n\r #/:<>?@[\\\]^|]/;
var LEADING_C0_CONTROL_OR_SPACE = /^[\u0000-\u0020]+/;
var TRAILING_C0_CONTROL_OR_SPACE = /(^|[^\u0000-\u0020])[\u0000-\u0020]+$/;
var TAB_AND_NEW_LINE = /[\t\n\r]/g;
/* eslint-enable regexp/no-control-character -- safe */
// eslint-disable-next-line no-unassigned-vars -- expected `undefined` value
var EOF;

// https://url.spec.whatwg.org/#ipv4-number-parser
var parseIPv4 = function (input) {
  var parts = split(input, '.');
  var partsLength, numbers, index, part, radix, number, ipv4;
  if (parts.length && parts[parts.length - 1] === '') {
    parts.length--;
  }
  partsLength = parts.length;
  if (partsLength > 4) return input;
  numbers = [];
  for (index = 0; index < partsLength; index++) {
    part = parts[index];
    if (part === '') return input;
    radix = 10;
    if (part.length > 1 && charAt(part, 0) === '0') {
      radix = exec(HEX_START, part) ? 16 : 8;
      part = stringSlice(part, radix === 8 ? 1 : 2);
    }
    if (part === '') {
      number = 0;
    } else {
      if (!exec(radix === 10 ? DEC : radix === 8 ? OCT : HEX, part)) return input;
      number = parseInt$1(part, radix);
    }
    push(numbers, number);
  }
  for (index = 0; index < partsLength; index++) {
    number = numbers[index];
    if (index === partsLength - 1) {
      if (number >= pow(256, 5 - partsLength)) return null;
    } else if (number > 255) return null;
  }
  ipv4 = pop(numbers);
  for (index = 0; index < numbers.length; index++) {
    ipv4 += numbers[index] * pow(256, 3 - index);
  }
  return ipv4;
};

// https://url.spec.whatwg.org/#concept-ipv6-parser
// eslint-disable-next-line max-statements -- TODO
var parseIPv6 = function (input) {
  var address = [0, 0, 0, 0, 0, 0, 0, 0];
  var pieceIndex = 0;
  var compress = null;
  var pointer = 0;
  var value, length, numbersSeen, ipv4Piece, number, swaps, swap;

  var chr = function () {
    return charAt(input, pointer);
  };

  if (chr() === ':') {
    if (charAt(input, 1) !== ':') return;
    pointer += 2;
    pieceIndex++;
    compress = pieceIndex;
  }
  while (chr()) {
    if (pieceIndex === 8) return;
    if (chr() === ':') {
      if (compress !== null) return;
      pointer++;
      pieceIndex++;
      compress = pieceIndex;
      continue;
    }
    value = length = 0;
    while (length < 4 && exec(HEX, chr())) {
      value = value * 16 + parseInt$1(chr(), 16);
      pointer++;
      length++;
    }
    if (chr() === '.') {
      if (length === 0) return;
      pointer -= length;
      if (pieceIndex > 6) return;
      numbersSeen = 0;
      while (chr()) {
        ipv4Piece = null;
        if (numbersSeen > 0) {
          if (chr() === '.' && numbersSeen < 4) pointer++;
          else return;
        }
        if (!exec(DIGIT, chr())) return;
        while (exec(DIGIT, chr())) {
          number = parseInt$1(chr(), 10);
          if (ipv4Piece === null) ipv4Piece = number;
          else if (ipv4Piece === 0) return;
          else ipv4Piece = ipv4Piece * 10 + number;
          if (ipv4Piece > 255) return;
          pointer++;
        }
        address[pieceIndex] = address[pieceIndex] * 256 + ipv4Piece;
        numbersSeen++;
        if (numbersSeen === 2 || numbersSeen === 4) pieceIndex++;
      }
      if (numbersSeen !== 4) return;
      break;
    } else if (chr() === ':') {
      pointer++;
      if (!chr()) return;
    } else if (chr()) return;
    address[pieceIndex++] = value;
  }
  if (compress !== null) {
    swaps = pieceIndex - compress;
    pieceIndex = 7;
    while (pieceIndex !== 0 && swaps > 0) {
      swap = address[pieceIndex];
      address[pieceIndex--] = address[compress + swaps - 1];
      address[compress + --swaps] = swap;
    }
  } else if (pieceIndex !== 8) return;
  return address;
};

var findLongestZeroSequence = function (ipv6) {
  var maxIndex = null;
  var maxLength = 1;
  var currStart = null;
  var currLength = 0;
  var index = 0;
  for (; index < 8; index++) {
    if (ipv6[index] !== 0) {
      if (currLength > maxLength) {
        maxIndex = currStart;
        maxLength = currLength;
      }
      currStart = null;
      currLength = 0;
    } else {
      if (currStart === null) currStart = index;
      ++currLength;
    }
  }
  return currLength > maxLength ? currStart : maxIndex;
};

// https://url.spec.whatwg.org/#host-serializing
var serializeHost = function (host) {
  var result, index, compress, ignore0;

  // ipv4
  if (typeof host == 'number') {
    result = [];
    for (index = 0; index < 4; index++) {
      unshift(result, host % 256);
      host = floor(host / 256);
    }
    return join(result, '.');
  }

  // ipv6
  if (typeof host == 'object') {
    result = '';
    compress = findLongestZeroSequence(host);
    for (index = 0; index < 8; index++) {
      if (ignore0 && host[index] === 0) continue;
      if (ignore0) ignore0 = false;
      if (compress === index) {
        result += index ? ':' : '::';
        ignore0 = true;
      } else {
        result += numberToString(host[index], 16);
        if (index < 7) result += ':';
      }
    }
    return '[' + result + ']';
  }

  return host;
};

var C0ControlPercentEncodeSet = {};
var fragmentPercentEncodeSet = assign({}, C0ControlPercentEncodeSet, {
  ' ': 1, '"': 1, '<': 1, '>': 1, '`': 1
});
var pathPercentEncodeSet = assign({}, fragmentPercentEncodeSet, {
  '#': 1, '?': 1, '{': 1, '}': 1
});
var userinfoPercentEncodeSet = assign({}, pathPercentEncodeSet, {
  '/': 1, ':': 1, ';': 1, '=': 1, '@': 1, '[': 1, '\\': 1, ']': 1, '^': 1, '|': 1
});

var percentEncode = function (chr, set) {
  var code = codeAt(chr, 0);
  return code > 0x20 && code < 0x7F && !hasOwn(set, chr) ? chr : encodeURIComponent(chr);
};

// https://url.spec.whatwg.org/#special-scheme
var specialSchemes = {
  ftp: 21,
  file: null,
  http: 80,
  https: 443,
  ws: 80,
  wss: 443
};

// https://url.spec.whatwg.org/#windows-drive-letter
var isWindowsDriveLetter = function (string, normalized) {
  var second;
  return string.length === 2 && exec(ALPHA, charAt(string, 0))
    && ((second = charAt(string, 1)) === ':' || (!normalized && second === '|'));
};

// https://url.spec.whatwg.org/#start-with-a-windows-drive-letter
var startsWithWindowsDriveLetter = function (string) {
  var third;
  return string.length > 1 && isWindowsDriveLetter(stringSlice(string, 0, 2)) && (
    string.length === 2 ||
    ((third = charAt(string, 2)) === '/' || third === '\\' || third === '?' || third === '#')
  );
};

// https://url.spec.whatwg.org/#single-dot-path-segment
var isSingleDot = function (segment) {
  return segment === '.' || toLowerCase(segment) === '%2e';
};

// https://url.spec.whatwg.org/#double-dot-path-segment
var isDoubleDot = function (segment) {
  segment = toLowerCase(segment);
  return segment === '..' || segment === '%2e.' || segment === '.%2e' || segment === '%2e%2e';
};

// States:
var SCHEME_START = {};
var SCHEME = {};
var NO_SCHEME = {};
var SPECIAL_RELATIVE_OR_AUTHORITY = {};
var PATH_OR_AUTHORITY = {};
var RELATIVE = {};
var RELATIVE_SLASH = {};
var SPECIAL_AUTHORITY_SLASHES = {};
var SPECIAL_AUTHORITY_IGNORE_SLASHES = {};
var AUTHORITY = {};
var HOST = {};
var HOSTNAME = {};
var PORT = {};
var FILE = {};
var FILE_SLASH = {};
var FILE_HOST = {};
var PATH_START = {};
var PATH = {};
var CANNOT_BE_A_BASE_URL_PATH = {};
var QUERY = {};
var FRAGMENT = {};

var URLState = function (url, isBase, base) {
  var urlString = $toString(url);
  var baseState, failure, searchParams;
  if (isBase) {
    failure = this.parse(urlString);
    if (failure) throw new TypeError$1(failure);
    this.searchParams = null;
  } else {
    if (base !== undefined) baseState = new URLState(base, true);
    failure = this.parse(urlString, null, baseState);
    if (failure) throw new TypeError$1(failure);
    searchParams = getInternalSearchParamsState(new URLSearchParams$1());
    searchParams.bindURL(this);
    this.searchParams = searchParams;
  }
};

URLState.prototype = {
  type: 'URL',
  // https://url.spec.whatwg.org/#url-parsing
  // eslint-disable-next-line max-statements -- TODO
  parse: function (input, stateOverride, base) {
    var url = this;
    var state = stateOverride || SCHEME_START;
    var pointer = 0;
    var buffer = '';
    var seenAt = false;
    var seenBracket = false;
    var seenPasswordToken = false;
    var codePoints, chr, bufferCodePoints, failure;

    input = $toString(input);

    if (!stateOverride) {
      url.scheme = '';
      url.username = '';
      url.password = '';
      url.host = null;
      url.port = null;
      url.path = [];
      url.query = null;
      url.fragment = null;
      url.cannotBeABaseURL = false;
      input = replace(input, LEADING_C0_CONTROL_OR_SPACE, '');
      input = replace(input, TRAILING_C0_CONTROL_OR_SPACE, '$1');
    }

    input = replace(input, TAB_AND_NEW_LINE, '');

    codePoints = arrayFrom(input);

    while (pointer <= codePoints.length) {
      chr = codePoints[pointer];
      switch (state) {
        case SCHEME_START:
          if (chr && exec(ALPHA, chr)) {
            buffer += toLowerCase(chr);
            state = SCHEME;
          } else if (!stateOverride) {
            state = NO_SCHEME;
            continue;
          } else return INVALID_SCHEME;
          break;

        case SCHEME:
          if (chr && (exec(ALPHANUMERIC, chr) || chr === '+' || chr === '-' || chr === '.')) {
            buffer += toLowerCase(chr);
          } else if (chr === ':') {
            if (stateOverride && (
              (url.isSpecial() !== hasOwn(specialSchemes, buffer)) ||
              (buffer === 'file' && (url.includesCredentials() || url.port !== null)) ||
              (url.scheme === 'file' && !url.host)
            )) return;
            url.scheme = buffer;
            if (stateOverride) {
              if (url.isSpecial() && specialSchemes[url.scheme] === url.port) url.port = null;
              return;
            }
            buffer = '';
            if (url.scheme === 'file') {
              state = FILE;
            } else if (url.isSpecial() && base && base.scheme === url.scheme) {
              state = SPECIAL_RELATIVE_OR_AUTHORITY;
            } else if (url.isSpecial()) {
              state = SPECIAL_AUTHORITY_SLASHES;
            } else if (codePoints[pointer + 1] === '/') {
              state = PATH_OR_AUTHORITY;
              pointer++;
            } else {
              url.cannotBeABaseURL = true;
              push(url.path, '');
              state = CANNOT_BE_A_BASE_URL_PATH;
            }
          } else if (!stateOverride) {
            buffer = '';
            state = NO_SCHEME;
            pointer = 0;
            continue;
          } else return INVALID_SCHEME;
          break;

        case NO_SCHEME:
          if (!base || (base.cannotBeABaseURL && chr !== '#')) return INVALID_SCHEME;
          if (base.cannotBeABaseURL && chr === '#') {
            url.scheme = base.scheme;
            url.path = arraySlice(base.path);
            url.query = base.query;
            url.fragment = '';
            url.cannotBeABaseURL = true;
            state = FRAGMENT;
            break;
          }
          state = base.scheme === 'file' ? FILE : RELATIVE;
          continue;

        case SPECIAL_RELATIVE_OR_AUTHORITY:
          if (chr === '/' && codePoints[pointer + 1] === '/') {
            state = SPECIAL_AUTHORITY_IGNORE_SLASHES;
            pointer++;
          } else {
            state = RELATIVE;
            continue;
          } break;

        case PATH_OR_AUTHORITY:
          if (chr === '/') {
            state = AUTHORITY;
            break;
          } else {
            state = PATH;
            continue;
          }

        case RELATIVE:
          url.scheme = base.scheme;
          if (chr === EOF) {
            url.username = base.username;
            url.password = base.password;
            url.host = base.host;
            url.port = base.port;
            url.path = arraySlice(base.path);
            url.query = base.query;
          } else if (chr === '/' || (chr === '\\' && url.isSpecial())) {
            state = RELATIVE_SLASH;
          } else if (chr === '?') {
            url.username = base.username;
            url.password = base.password;
            url.host = base.host;
            url.port = base.port;
            url.path = arraySlice(base.path);
            url.query = '';
            state = QUERY;
          } else if (chr === '#') {
            url.username = base.username;
            url.password = base.password;
            url.host = base.host;
            url.port = base.port;
            url.path = arraySlice(base.path);
            url.query = base.query;
            url.fragment = '';
            state = FRAGMENT;
          } else {
            url.username = base.username;
            url.password = base.password;
            url.host = base.host;
            url.port = base.port;
            url.path = arraySlice(base.path);
            url.path.length--;
            state = PATH;
            continue;
          } break;

        case RELATIVE_SLASH:
          if (url.isSpecial() && (chr === '/' || chr === '\\')) {
            state = SPECIAL_AUTHORITY_IGNORE_SLASHES;
          } else if (chr === '/') {
            state = AUTHORITY;
          } else {
            url.username = base.username;
            url.password = base.password;
            url.host = base.host;
            url.port = base.port;
            state = PATH;
            continue;
          } break;

        case SPECIAL_AUTHORITY_SLASHES:
          state = SPECIAL_AUTHORITY_IGNORE_SLASHES;
          if (chr !== '/' || charAt(buffer, pointer + 1) !== '/') continue;
          pointer++;
          break;

        case SPECIAL_AUTHORITY_IGNORE_SLASHES:
          if (chr !== '/' && chr !== '\\') {
            state = AUTHORITY;
            continue;
          } break;

        case AUTHORITY:
          if (chr === '@') {
            if (seenAt) buffer = '%40' + buffer;
            seenAt = true;
            bufferCodePoints = arrayFrom(buffer);
            for (var i = 0; i < bufferCodePoints.length; i++) {
              var codePoint = bufferCodePoints[i];
              if (codePoint === ':' && !seenPasswordToken) {
                seenPasswordToken = true;
                continue;
              }
              var encodedCodePoints = percentEncode(codePoint, userinfoPercentEncodeSet);
              if (seenPasswordToken) url.password += encodedCodePoints;
              else url.username += encodedCodePoints;
            }
            buffer = '';
          } else if (
            chr === EOF || chr === '/' || chr === '?' || chr === '#' ||
            (chr === '\\' && url.isSpecial())
          ) {
            if (seenAt && buffer === '') return INVALID_AUTHORITY;
            pointer -= arrayFrom(buffer).length + 1;
            buffer = '';
            state = HOST;
          } else buffer += chr;
          break;

        case HOST:
        case HOSTNAME:
          if (stateOverride && url.scheme === 'file') {
            state = FILE_HOST;
            continue;
          } else if (chr === ':' && !seenBracket) {
            if (buffer === '') return INVALID_HOST;
            failure = url.parseHost(buffer);
            if (failure) return failure;
            buffer = '';
            state = PORT;
            if (stateOverride === HOSTNAME) return;
          } else if (
            chr === EOF || chr === '/' || chr === '?' || chr === '#' ||
            (chr === '\\' && url.isSpecial())
          ) {
            if (url.isSpecial() && buffer === '') return INVALID_HOST;
            if (stateOverride && buffer === '' && (url.includesCredentials() || url.port !== null)) return;
            failure = url.parseHost(buffer);
            if (failure) return failure;
            buffer = '';
            state = PATH_START;
            if (stateOverride) return;
            continue;
          } else {
            if (chr === '[') seenBracket = true;
            else if (chr === ']') seenBracket = false;
            buffer += chr;
          } break;

        case PORT:
          if (exec(DIGIT, chr)) {
            buffer += chr;
          } else if (
            chr === EOF || chr === '/' || chr === '?' || chr === '#' ||
            (chr === '\\' && url.isSpecial()) ||
            stateOverride
          ) {
            if (buffer !== '') {
              var port = parseInt$1(buffer, 10);
              if (port > 0xFFFF) return INVALID_PORT;
              url.port = (url.isSpecial() && port === specialSchemes[url.scheme]) ? null : port;
              buffer = '';
            }
            if (stateOverride) return;
            state = PATH_START;
            continue;
          } else return INVALID_PORT;
          break;

        case FILE:
          url.scheme = 'file';
          if (chr === '/' || chr === '\\') state = FILE_SLASH;
          else if (base && base.scheme === 'file') {
            switch (chr) {
              case EOF:
                url.host = base.host;
                url.path = arraySlice(base.path);
                url.query = base.query;
                break;
              case '?':
                url.host = base.host;
                url.path = arraySlice(base.path);
                url.query = '';
                state = QUERY;
                break;
              case '#':
                url.host = base.host;
                url.path = arraySlice(base.path);
                url.query = base.query;
                url.fragment = '';
                state = FRAGMENT;
                break;
              default:
                if (!startsWithWindowsDriveLetter(join(arraySlice(codePoints, pointer), ''))) {
                  url.host = base.host;
                  url.path = arraySlice(base.path);
                  url.shortenPath();
                }
                state = PATH;
                continue;
            }
          } else {
            state = PATH;
            continue;
          } break;

        case FILE_SLASH:
          if (chr === '/' || chr === '\\') {
            state = FILE_HOST;
            break;
          }
          if (base && base.scheme === 'file' && !startsWithWindowsDriveLetter(join(arraySlice(codePoints, pointer), ''))) {
            if (isWindowsDriveLetter(base.path[0], true)) push(url.path, base.path[0]);
            else url.host = base.host;
          }
          state = PATH;
          continue;

        case FILE_HOST:
          if (chr === EOF || chr === '/' || chr === '\\' || chr === '?' || chr === '#') {
            if (!stateOverride && isWindowsDriveLetter(buffer)) {
              state = PATH;
            } else if (buffer === '') {
              url.host = '';
              if (stateOverride) return;
              state = PATH_START;
            } else {
              failure = url.parseHost(buffer);
              if (failure) return failure;
              if (url.host === 'localhost') url.host = '';
              if (stateOverride) return;
              buffer = '';
              state = PATH_START;
            } continue;
          } else buffer += chr;
          break;

        case PATH_START:
          if (url.isSpecial()) {
            state = PATH;
            if (chr !== '/' && chr !== '\\') continue;
          } else if (!stateOverride && chr === '?') {
            url.query = '';
            state = QUERY;
          } else if (!stateOverride && chr === '#') {
            url.fragment = '';
            state = FRAGMENT;
          } else if (chr !== EOF) {
            state = PATH;
            if (chr !== '/') continue;
          } break;

        case PATH:
          if (
            chr === EOF || chr === '/' ||
            (chr === '\\' && url.isSpecial()) ||
            (!stateOverride && (chr === '?' || chr === '#'))
          ) {
            if (isDoubleDot(buffer)) {
              url.shortenPath();
              if (chr !== '/' && !(chr === '\\' && url.isSpecial())) {
                push(url.path, '');
              }
            } else if (isSingleDot(buffer)) {
              if (chr !== '/' && !(chr === '\\' && url.isSpecial())) {
                push(url.path, '');
              }
            } else {
              if (url.scheme === 'file' && !url.path.length && isWindowsDriveLetter(buffer)) {
                if (url.host) url.host = '';
                buffer = charAt(buffer, 0) + ':'; // normalize windows drive letter
              }
              push(url.path, buffer);
            }
            buffer = '';
            if (url.scheme === 'file' && (chr === EOF || chr === '?' || chr === '#')) {
              while (url.path.length > 1 && url.path[0] === '') {
                shift(url.path);
              }
            }
            if (chr === '?') {
              url.query = '';
              state = QUERY;
            } else if (chr === '#') {
              url.fragment = '';
              state = FRAGMENT;
            }
          } else {
            buffer += percentEncode(chr, pathPercentEncodeSet);
          } break;

        case CANNOT_BE_A_BASE_URL_PATH:
          if (chr === '?') {
            url.query = '';
            state = QUERY;
          } else if (chr === '#') {
            url.fragment = '';
            state = FRAGMENT;
          } else if (chr !== EOF) {
            url.path[0] += percentEncode(chr, C0ControlPercentEncodeSet);
          } break;

        case QUERY:
          if (!stateOverride && chr === '#') {
            url.fragment = '';
            state = FRAGMENT;
          } else if (chr !== EOF) {
            if (chr === "'" && url.isSpecial()) url.query += '%27';
            else if (chr === '#') url.query += '%23';
            else url.query += percentEncode(chr, C0ControlPercentEncodeSet);
          } break;

        case FRAGMENT:
          if (chr !== EOF) url.fragment += percentEncode(chr, fragmentPercentEncodeSet);
          break;
      }

      pointer++;
    }
  },
  // https://url.spec.whatwg.org/#host-parsing
  parseHost: function (input) {
    var result, codePoints, index;
    if (charAt(input, 0) === '[') {
      if (charAt(input, input.length - 1) !== ']') return INVALID_HOST;
      result = parseIPv6(stringSlice(input, 1, -1));
      if (!result) return INVALID_HOST;
      this.host = result;
    // opaque host
    } else if (!this.isSpecial()) {
      if (exec(FORBIDDEN_HOST_CODE_POINT_EXCLUDING_PERCENT, input)) return INVALID_HOST;
      result = '';
      codePoints = arrayFrom(input);
      for (index = 0; index < codePoints.length; index++) {
        result += percentEncode(codePoints[index], C0ControlPercentEncodeSet);
      }
      this.host = result;
    } else {
      input = toASCII(input);
      if (exec(FORBIDDEN_HOST_CODE_POINT, input)) return INVALID_HOST;
      result = parseIPv4(input);
      if (result === null) return INVALID_HOST;
      this.host = result;
    }
  },
  // https://url.spec.whatwg.org/#cannot-have-a-username-password-port
  cannotHaveUsernamePasswordPort: function () {
    return !this.host || this.cannotBeABaseURL || this.scheme === 'file';
  },
  // https://url.spec.whatwg.org/#include-credentials
  includesCredentials: function () {
    return this.username !== '' || this.password !== '';
  },
  // https://url.spec.whatwg.org/#is-special
  isSpecial: function () {
    return hasOwn(specialSchemes, this.scheme);
  },
  // https://url.spec.whatwg.org/#shorten-a-urls-path
  shortenPath: function () {
    var path = this.path;
    var pathSize = path.length;
    if (pathSize && (this.scheme !== 'file' || pathSize !== 1 || !isWindowsDriveLetter(path[0], true))) {
      path.length--;
    }
  },
  // https://url.spec.whatwg.org/#concept-url-serializer
  serialize: function () {
    var url = this;
    var scheme = url.scheme;
    var username = url.username;
    var password = url.password;
    var host = url.host;
    var port = url.port;
    var path = url.path;
    var query = url.query;
    var fragment = url.fragment;
    var output = scheme + ':';
    if (host !== null) {
      output += '//';
      if (url.includesCredentials()) {
        output += username + (password ? ':' + password : '') + '@';
      }
      output += serializeHost(host);
      if (port !== null) output += ':' + port;
    } else if (scheme === 'file') output += '//';
    output += url.cannotBeABaseURL ? path[0] : path.length ? '/' + join(path, '/') : '';
    if (query !== null) output += '?' + query;
    if (fragment !== null) output += '#' + fragment;
    return output;
  },
  // https://url.spec.whatwg.org/#dom-url-href
  setHref: function (href) {
    var failure = this.parse(href);
    if (failure) throw new TypeError$1(failure);
    this.searchParams.update();
  },
  // https://url.spec.whatwg.org/#dom-url-origin
  getOrigin: function () {
    var scheme = this.scheme;
    var port = this.port;
    if (scheme === 'blob') try {
      return new URLConstructor(scheme.path[0]).origin;
    } catch (error) {
      return 'null';
    }
    if (scheme === 'file' || !this.isSpecial()) return 'null';
    return scheme + '://' + serializeHost(this.host) + (port !== null ? ':' + port : '');
  },
  // https://url.spec.whatwg.org/#dom-url-protocol
  getProtocol: function () {
    return this.scheme + ':';
  },
  setProtocol: function (protocol) {
    this.parse($toString(protocol) + ':', SCHEME_START);
  },
  // https://url.spec.whatwg.org/#dom-url-username
  getUsername: function () {
    return this.username;
  },
  setUsername: function (username) {
    var codePoints = arrayFrom($toString(username));
    if (this.cannotHaveUsernamePasswordPort()) return;
    this.username = '';
    for (var i = 0; i < codePoints.length; i++) {
      this.username += percentEncode(codePoints[i], userinfoPercentEncodeSet);
    }
  },
  // https://url.spec.whatwg.org/#dom-url-password
  getPassword: function () {
    return this.password;
  },
  setPassword: function (password) {
    var codePoints = arrayFrom($toString(password));
    if (this.cannotHaveUsernamePasswordPort()) return;
    this.password = '';
    for (var i = 0; i < codePoints.length; i++) {
      this.password += percentEncode(codePoints[i], userinfoPercentEncodeSet);
    }
  },
  // https://url.spec.whatwg.org/#dom-url-host
  getHost: function () {
    var host = this.host;
    var port = this.port;
    return host === null ? ''
      : port === null ? serializeHost(host)
      : serializeHost(host) + ':' + port;
  },
  setHost: function (host) {
    if (this.cannotBeABaseURL) return;
    this.parse(host, HOST);
  },
  // https://url.spec.whatwg.org/#dom-url-hostname
  getHostname: function () {
    var host = this.host;
    return host === null ? '' : serializeHost(host);
  },
  setHostname: function (hostname) {
    if (this.cannotBeABaseURL) return;
    this.parse(hostname, HOSTNAME);
  },
  // https://url.spec.whatwg.org/#dom-url-port
  getPort: function () {
    var port = this.port;
    return port === null ? '' : $toString(port);
  },
  setPort: function (port) {
    if (this.cannotHaveUsernamePasswordPort()) return;
    port = $toString(port);
    if (port === '') this.port = null;
    else this.parse(port, PORT);
  },
  // https://url.spec.whatwg.org/#dom-url-pathname
  getPathname: function () {
    var path = this.path;
    return this.cannotBeABaseURL ? path[0] : path.length ? '/' + join(path, '/') : '';
  },
  setPathname: function (pathname) {
    if (this.cannotBeABaseURL) return;
    this.path = [];
    this.parse(pathname, PATH_START);
  },
  // https://url.spec.whatwg.org/#dom-url-search
  getSearch: function () {
    var query = this.query;
    return query ? '?' + query : '';
  },
  setSearch: function (search) {
    search = $toString(search);
    if (search === '') {
      this.query = null;
    } else {
      if (charAt(search, 0) === '?') search = stringSlice(search, 1);
      this.query = '';
      this.parse(search, QUERY);
    }
    this.searchParams.update();
  },
  // https://url.spec.whatwg.org/#dom-url-searchparams
  getSearchParams: function () {
    return this.searchParams.facade;
  },
  // https://url.spec.whatwg.org/#dom-url-hash
  getHash: function () {
    var fragment = this.fragment;
    return fragment ? '#' + fragment : '';
  },
  setHash: function (hash) {
    hash = $toString(hash);
    if (hash === '') {
      this.fragment = null;
      return;
    }
    if (charAt(hash, 0) === '#') hash = stringSlice(hash, 1);
    this.fragment = '';
    this.parse(hash, FRAGMENT);
  },
  update: function () {
    this.query = this.searchParams.serialize() || null;
  }
};

// `URL` constructor
// https://url.spec.whatwg.org/#url-class
var URLConstructor = function URL(url /* , base */) {
  var that = anInstance(this, URLPrototype);
  var base = validateArgumentsLength$2(arguments.length, 1) > 1 ? arguments[1] : undefined;
  var state = setInternalState(that, new URLState(url, false, base));
  if (!DESCRIPTORS) {
    that.href = state.serialize();
    that.origin = state.getOrigin();
    that.protocol = state.getProtocol();
    that.username = state.getUsername();
    that.password = state.getPassword();
    that.host = state.getHost();
    that.hostname = state.getHostname();
    that.port = state.getPort();
    that.pathname = state.getPathname();
    that.search = state.getSearch();
    that.searchParams = state.getSearchParams();
    that.hash = state.getHash();
  }
};

var URLPrototype = URLConstructor.prototype;

var accessorDescriptor = function (getter, setter) {
  return {
    get: function () {
      return getInternalURLState(this)[getter]();
    },
    set: setter && function (value) {
      return getInternalURLState(this)[setter](value);
    },
    configurable: true,
    enumerable: true
  };
};

if (DESCRIPTORS) {
  // `URL.prototype.href` accessors pair
  // https://url.spec.whatwg.org/#dom-url-href
  defineBuiltInAccessor(URLPrototype, 'href', accessorDescriptor('serialize', 'setHref'));
  // `URL.prototype.origin` getter
  // https://url.spec.whatwg.org/#dom-url-origin
  defineBuiltInAccessor(URLPrototype, 'origin', accessorDescriptor('getOrigin'));
  // `URL.prototype.protocol` accessors pair
  // https://url.spec.whatwg.org/#dom-url-protocol
  defineBuiltInAccessor(URLPrototype, 'protocol', accessorDescriptor('getProtocol', 'setProtocol'));
  // `URL.prototype.username` accessors pair
  // https://url.spec.whatwg.org/#dom-url-username
  defineBuiltInAccessor(URLPrototype, 'username', accessorDescriptor('getUsername', 'setUsername'));
  // `URL.prototype.password` accessors pair
  // https://url.spec.whatwg.org/#dom-url-password
  defineBuiltInAccessor(URLPrototype, 'password', accessorDescriptor('getPassword', 'setPassword'));
  // `URL.prototype.host` accessors pair
  // https://url.spec.whatwg.org/#dom-url-host
  defineBuiltInAccessor(URLPrototype, 'host', accessorDescriptor('getHost', 'setHost'));
  // `URL.prototype.hostname` accessors pair
  // https://url.spec.whatwg.org/#dom-url-hostname
  defineBuiltInAccessor(URLPrototype, 'hostname', accessorDescriptor('getHostname', 'setHostname'));
  // `URL.prototype.port` accessors pair
  // https://url.spec.whatwg.org/#dom-url-port
  defineBuiltInAccessor(URLPrototype, 'port', accessorDescriptor('getPort', 'setPort'));
  // `URL.prototype.pathname` accessors pair
  // https://url.spec.whatwg.org/#dom-url-pathname
  defineBuiltInAccessor(URLPrototype, 'pathname', accessorDescriptor('getPathname', 'setPathname'));
  // `URL.prototype.search` accessors pair
  // https://url.spec.whatwg.org/#dom-url-search
  defineBuiltInAccessor(URLPrototype, 'search', accessorDescriptor('getSearch', 'setSearch'));
  // `URL.prototype.searchParams` getter
  // https://url.spec.whatwg.org/#dom-url-searchparams
  defineBuiltInAccessor(URLPrototype, 'searchParams', accessorDescriptor('getSearchParams'));
  // `URL.prototype.hash` accessors pair
  // https://url.spec.whatwg.org/#dom-url-hash
  defineBuiltInAccessor(URLPrototype, 'hash', accessorDescriptor('getHash', 'setHash'));
}

// `URL.prototype.toJSON` method
// https://url.spec.whatwg.org/#dom-url-tojson
defineBuiltIn(URLPrototype, 'toJSON', function toJSON() {
  return getInternalURLState(this).serialize();
}, { enumerable: true });

// `URL.prototype.toString` method
// https://url.spec.whatwg.org/#URL-stringification-behavior
defineBuiltIn(URLPrototype, 'toString', function toString() {
  return getInternalURLState(this).serialize();
}, { enumerable: true });

if (NativeURL) {
  var nativeCreateObjectURL = NativeURL.createObjectURL;
  var nativeRevokeObjectURL = NativeURL.revokeObjectURL;
  // `URL.createObjectURL` method
  // https://developer.mozilla.org/en-US/docs/Web/API/URL/createObjectURL
  if (nativeCreateObjectURL) defineBuiltIn(URLConstructor, 'createObjectURL', bind(nativeCreateObjectURL, NativeURL));
  // `URL.revokeObjectURL` method
  // https://developer.mozilla.org/en-US/docs/Web/API/URL/revokeObjectURL
  if (nativeRevokeObjectURL) defineBuiltIn(URLConstructor, 'revokeObjectURL', bind(nativeRevokeObjectURL, NativeURL));
}

setToStringTag(URLConstructor, 'URL');

$$2({ global: true, forced: !USE_NATIVE_URL$2, sham: !DESCRIPTORS }, {
  URL: URLConstructor
});

var $$1 = _export;
var getBuiltIn$1 = getBuiltIn$6;
var fails = fails$d;
var validateArgumentsLength$1 = validateArgumentsLength$4;
var toString$1 = toString$4;
var USE_NATIVE_URL$1 = urlConstructorDetection;

var URL$3 = getBuiltIn$1('URL');

// https://github.com/nodejs/node/issues/47505
// https://github.com/denoland/deno/issues/18893
var THROWS_WITHOUT_ARGUMENTS = USE_NATIVE_URL$1 && fails(function () {
  URL$3.canParse();
});

// Bun ~ 1.0.30 bug
// https://github.com/oven-sh/bun/issues/9250
var WRONG_ARITY = fails(function () {
  return URL$3.canParse.length !== 1;
});

// `URL.canParse` method
// https://url.spec.whatwg.org/#dom-url-canparse
$$1({ target: 'URL', stat: true, forced: !THROWS_WITHOUT_ARGUMENTS || WRONG_ARITY }, {
  canParse: function canParse(url) {
    var length = validateArgumentsLength$1(arguments.length, 1);
    var urlString = toString$1(url);
    var base = length < 2 || arguments[1] === undefined ? undefined : toString$1(arguments[1]);
    try {
      return !!new URL$3(urlString, base);
    } catch (error) {
      return false;
    }
  }
});

var $ = _export;
var getBuiltIn = getBuiltIn$6;
var validateArgumentsLength = validateArgumentsLength$4;
var toString = toString$4;
var USE_NATIVE_URL = urlConstructorDetection;

var URL$2 = getBuiltIn('URL');

// `URL.parse` method
// https://url.spec.whatwg.org/#dom-url-canparse
$({ target: 'URL', stat: true, forced: !USE_NATIVE_URL }, {
  parse: function parse(url) {
    var length = validateArgumentsLength(arguments.length, 1);
    var urlString = toString(url);
    var base = length < 2 || arguments[1] === undefined ? undefined : toString(arguments[1]);
    try {
      return new URL$2(urlString, base);
    } catch (error) {
      return null;
    }
  }
});

var path = path$4;

var url$4 = path.URL;

var parent$2 = url$4;

var url$3 = parent$2;

var parent$1 = url$3;

var url$2 = parent$1;

var parent = url$2;

var url$1 = parent;

var url = url$1;

var URL$1 = /*@__PURE__*/getDefaultExportFromCjs(url);

/**
 * @internal
 */
function _removeTrailingSlash(s) {
  return s.replace(/\/+$/g, "");
}

/**
 * @internal
 */
var _BaseAPIClient = /*#__PURE__*/function () {
  function _BaseAPIClient(options) {
    this._fetch = options.fetch;
    this._Request = options.Request;
    this.dpopProvider = options.dpopProvider;
  }
  var _proto = _BaseAPIClient.prototype;
  _proto._prepareHeaders = /*#__PURE__*/function () {
    var _prepareHeaders2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee() {
      var _this$_delegate;
      var headers, accessToken;
      return _regeneratorRuntime.wrap(function (_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            headers = {};
            accessToken = (_this$_delegate = this._delegate) == null ? void 0 : _this$_delegate.getAccessToken();
            if (accessToken != null) {
              headers["authorization"] = "Bearer ".concat(accessToken);
            }
            if (this.userAgent != null) {
              headers["user-agent"] = this.userAgent;
            }
            return _context.abrupt("return", headers);
          case 1:
          case "end":
            return _context.stop();
        }
      }, _callee, this);
    }));
    function _prepareHeaders() {
      return _prepareHeaders2.apply(this, arguments);
    }
    return _prepareHeaders;
  }();
  _proto._doFetch = /*#__PURE__*/function () {
    var _doFetch2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2(request) {
      var dpopJWT;
      return _regeneratorRuntime.wrap(function (_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            if (!(this.dpopProvider != null)) {
              _context2.next = 2;
              break;
            }
            _context2.next = 1;
            return this.dpopProvider.generateDPoPProof({
              htm: request.method,
              htu: request.url
            });
          case 1:
            dpopJWT = _context2.sent;
            if (dpopJWT) {
              request.headers.set("DPoP", dpopJWT);
            }
          case 2:
            return _context2.abrupt("return", this._fetch(request));
          case 3:
          case "end":
            return _context2.stop();
        }
      }, _callee2, this);
    }));
    function _doFetch(_x) {
      return _doFetch2.apply(this, arguments);
    }
    return _doFetch;
  }();
  _proto._fetchWithoutRefresh = /*#__PURE__*/function () {
    var _fetchWithoutRefresh2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee3(url, init) {
      var request;
      return _regeneratorRuntime.wrap(function (_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            request = new this._Request(url, init);
            return _context3.abrupt("return", this._doFetch(request));
          case 1:
          case "end":
            return _context3.stop();
        }
      }, _callee3, this);
    }));
    function _fetchWithoutRefresh(_x2, _x3) {
      return _fetchWithoutRefresh2.apply(this, arguments);
    }
    return _fetchWithoutRefresh;
  }();
  _proto.fetch = /*#__PURE__*/function () {
    var _fetch = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee4(input, init) {
      var request, headers, _i2, _Object$keys2, key;
      return _regeneratorRuntime.wrap(function (_context4) {
        while (1) switch (_context4.prev = _context4.next) {
          case 0:
            if (!(this._delegate == null)) {
              _context4.next = 1;
              break;
            }
            throw new AuthgearError("missing delegate in api client");
          case 1:
            _context4.next = 2;
            return this._delegate.refreshAccessTokenIfNeeded();
          case 2:
            request = new this._Request(input, init);
            _context4.next = 3;
            return this._prepareHeaders();
          case 3:
            headers = _context4.sent;
            for (_i2 = 0, _Object$keys2 = Object.keys(headers); _i2 < _Object$keys2.length; _i2++) {
              key = _Object$keys2[_i2];
              request.headers.set(key, headers[key]);
            }
            return _context4.abrupt("return", this._doFetch(request));
          case 4:
          case "end":
            return _context4.stop();
        }
      }, _callee4, this);
    }));
    function fetch(_x4, _x5) {
      return _fetch.apply(this, arguments);
    }
    return fetch;
  }();
  _proto._requestJSON = /*#__PURE__*/function () {
    var _requestJSON2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee5(method, path, options) {
      var json, query, credentials, headers, body;
      return _regeneratorRuntime.wrap(function (_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            json = options.json, query = options.query, credentials = options.credentials;
            headers = {};
            if (json != null) {
              headers["content-type"] = "application/json";
            }
            body = json != null ? JSON.stringify(json) : undefined;
            return _context5.abrupt("return", this._request(method, path, {
              headers: headers,
              query: query,
              body: body,
              credentials: credentials
            }));
          case 1:
          case "end":
            return _context5.stop();
        }
      }, _callee5, this);
    }));
    function _requestJSON(_x6, _x7, _x8) {
      return _requestJSON2.apply(this, arguments);
    }
    return _requestJSON;
  }() // eslint-disable-next-line complexity
;
  _proto._request =
  /*#__PURE__*/
  function () {
    var _request2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee6(method, path, options) {
      var config, endpoint, headers, query, body, credentials, p, params, i, input, response, jsonBody;
      return _regeneratorRuntime.wrap(function (_context6) {
        while (1) switch (_context6.prev = _context6.next) {
          case 0:
            _context6.next = 1;
            return this._fetchOIDCConfiguration();
          case 1:
            config = _context6.sent;
            endpoint = new URL$1(config.authorization_endpoint).origin;
            headers = options.headers, query = options.query, body = options.body, credentials = options.credentials;
            p = path;
            if (query != null && query.length > 0) {
              params = new URLSearchParams$2();
              for (i = 0; i < query.length; ++i) {
                params.append(query[i][0], query[i][1]);
              }
              p += "?" + params.toString();
            }
            input = endpoint + "/" + p.replace(/^\//, "");
            _context6.next = 2;
            return this.fetch(input, {
              method: method,
              headers: headers != null ? headers : {},
              mode: "cors",
              credentials: credentials,
              body: body
            });
          case 2:
            response = _context6.sent;
            _context6.prev = 3;
            _context6.next = 4;
            return response.json();
          case 4:
            jsonBody = _context6.sent;
            _context6.next = 7;
            break;
          case 5:
            _context6.prev = 5;
            _context6["catch"](3);
            if (!(response.status < 200 || response.status >= 300)) {
              _context6.next = 6;
              break;
            }
            throw new ServerError("unexpected status code", "InternalError", "UnexpectedError", {
              status_code: response.status
            });
          case 6:
            throw new ServerError("failed to decode response JSON", "InternalError", "UnexpectedError");
          case 7:
            if (!jsonBody["result"]) {
              _context6.next = 8;
              break;
            }
            return _context6.abrupt("return", jsonBody["result"]);
          case 8:
            if (!jsonBody["error"]) {
              _context6.next = 9;
              break;
            }
            throw _decodeError(jsonBody["error"]);
          case 9:
            throw _decodeError(undefined);
          case 10:
          case "end":
            return _context6.stop();
        }
      }, _callee6, this, [[3, 5]]);
    }));
    function _request(_x9, _x0, _x1) {
      return _request2.apply(this, arguments);
    }
    return _request;
  }();
  _proto._post = /*#__PURE__*/function () {
    var _post2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee7(path, options) {
      return _regeneratorRuntime.wrap(function (_context7) {
        while (1) switch (_context7.prev = _context7.next) {
          case 0:
            return _context7.abrupt("return", this._requestJSON("POST", path, options));
          case 1:
          case "end":
            return _context7.stop();
        }
      }, _callee7, this);
    }));
    function _post(_x10, _x11) {
      return _post2.apply(this, arguments);
    }
    return _post;
  }();
  _proto._fetchOIDCRequest = /*#__PURE__*/function () {
    var _fetchOIDCRequest2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee8(url, init) {
      var resp, errJSON;
      return _regeneratorRuntime.wrap(function (_context8) {
        while (1) switch (_context8.prev = _context8.next) {
          case 0:
            _context8.next = 1;
            return this._fetchWithoutRefresh(url, init);
          case 1:
            resp = _context8.sent;
            if (!(resp.status === 200)) {
              _context8.next = 2;
              break;
            }
            return _context8.abrupt("return", resp);
          case 2:
            _context8.prev = 2;
            _context8.next = 3;
            return resp.json();
          case 3:
            errJSON = _context8.sent;
            _context8.next = 5;
            break;
          case 4:
            _context8.prev = 4;
            _context8["catch"](2);
            throw new ServerError("failed to decode response JSON", "InternalError", "UnexpectedError", {
              status_code: resp.status
            });
          case 5:
            throw new OAuthError({
              error: errJSON["error"],
              error_description: errJSON["error_description"]
            });
          case 6:
          case "end":
            return _context8.stop();
        }
      }, _callee8, this, [[2, 4]]);
    }));
    function _fetchOIDCRequest(_x12, _x13) {
      return _fetchOIDCRequest2.apply(this, arguments);
    }
    return _fetchOIDCRequest;
  }();
  _proto._fetchOIDCJSON = /*#__PURE__*/function () {
    var _fetchOIDCJSON2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee9(url, init) {
      var resp, jsonBody;
      return _regeneratorRuntime.wrap(function (_context9) {
        while (1) switch (_context9.prev = _context9.next) {
          case 0:
            _context9.next = 1;
            return this._fetchOIDCRequest(url, init);
          case 1:
            resp = _context9.sent;
            _context9.prev = 2;
            _context9.next = 3;
            return resp.json();
          case 3:
            jsonBody = _context9.sent;
            _context9.next = 5;
            break;
          case 4:
            _context9.prev = 4;
            _context9["catch"](2);
            throw new ServerError("failed to decode response JSON", "InternalError", "UnexpectedError");
          case 5:
            return _context9.abrupt("return", jsonBody);
          case 6:
          case "end":
            return _context9.stop();
        }
      }, _callee9, this, [[2, 4]]);
    }));
    function _fetchOIDCJSON(_x14, _x15) {
      return _fetchOIDCJSON2.apply(this, arguments);
    }
    return _fetchOIDCJSON;
  }();
  _proto._fetchOIDCConfiguration = /*#__PURE__*/function () {
    var _fetchOIDCConfiguration2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee0() {
      var endpoint;
      return _regeneratorRuntime.wrap(function (_context0) {
        while (1) switch (_context0.prev = _context0.next) {
          case 0:
            if (!(this.endpoint == null)) {
              _context0.next = 1;
              break;
            }
            throw new AuthgearError("missing endpoint in api client");
          case 1:
            endpoint = this.endpoint;
            if (this._config) {
              _context0.next = 3;
              break;
            }
            _context0.next = 2;
            return this._fetchOIDCJSON("".concat(endpoint, "/.well-known/openid-configuration"));
          case 2:
            this._config = _context0.sent;
          case 3:
            return _context0.abrupt("return", this._config);
          case 4:
          case "end":
            return _context0.stop();
        }
      }, _callee0, this);
    }));
    function _fetchOIDCConfiguration() {
      return _fetchOIDCConfiguration2.apply(this, arguments);
    }
    return _fetchOIDCConfiguration;
  }();
  _proto._oidcTokenRequest = /*#__PURE__*/function () {
    var _oidcTokenRequest2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee1(req) {
      var config, query, appendKeyAsString, _i4, _ref2, k, headers, credentials;
      return _regeneratorRuntime.wrap(function (_context1) {
        while (1) switch (_context1.prev = _context1.next) {
          case 0:
            _context1.next = 1;
            return this._fetchOIDCConfiguration();
          case 1:
            config = _context1.sent;
            query = new URLSearchParams$2();
            query.append("grant_type", req.grant_type);
            query.append("client_id", req.client_id);
            appendKeyAsString = function appendKeyAsString(key) {
              var value = req[key];
              if (value) {
                if (typeof value !== "string") {
                  throw new Error("value is not a string");
                }
                query.append(key, value);
              }
            };
            for (_i4 = 0, _ref2 = ["code", "redirect_uri", "code_verifier", "refresh_token", "jwt", "x_device_info", "requested_token_type", "subject_token", "subject_token_type", "actor_token", "actor_token_type", "audience"]; _i4 < _ref2.length; _i4++) {
              k = _ref2[_i4];
              appendKeyAsString(k);
            }
            if (req.scope != null) {
              query.append("scope", req.scope.join(" "));
            }
            headers = {
              "content-type": "application/x-www-form-urlencoded"
            };
            credentials = "include";
            if (req.access_token != null) {
              headers.authorization = "Bearer ".concat(req.access_token);
              credentials = "omit";
            }
            return _context1.abrupt("return", this._fetchOIDCJSON(config.token_endpoint, {
              method: "POST",
              headers: headers,
              body: query.toString(),
              mode: "cors",
              credentials: credentials
            }));
          case 2:
          case "end":
            return _context1.stop();
        }
      }, _callee1, this);
    }));
    function _oidcTokenRequest(_x16) {
      return _oidcTokenRequest2.apply(this, arguments);
    }
    return _oidcTokenRequest;
  }();
  _proto._setupBiometricRequest = /*#__PURE__*/function () {
    var _setupBiometricRequest2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee10(req) {
      var config, headers, query;
      return _regeneratorRuntime.wrap(function (_context10) {
        while (1) switch (_context10.prev = _context10.next) {
          case 0:
            _context10.next = 1;
            return this._fetchOIDCConfiguration();
          case 1:
            config = _context10.sent;
            headers = {
              authorization: "Bearer ".concat(req.access_token),
              "content-type": "application/x-www-form-urlencoded"
            };
            query = new URLSearchParams$2();
            query.append("grant_type", "urn:authgear:params:oauth:grant-type:biometric-request");
            query.append("client_id", req.client_id);
            query.append("jwt", req.jwt);
            _context10.next = 2;
            return this._fetchOIDCRequest(config.token_endpoint, {
              method: "POST",
              headers: headers,
              body: query.toString()
            });
          case 2:
          case "end":
            return _context10.stop();
        }
      }, _callee10, this);
    }));
    function _setupBiometricRequest(_x17) {
      return _setupBiometricRequest2.apply(this, arguments);
    }
    return _setupBiometricRequest;
  }();
  _proto._oidcUserInfoRequest = /*#__PURE__*/function () {
    var _oidcUserInfoRequest2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee11(accessToken) {
      var headers, credentials, config, response;
      return _regeneratorRuntime.wrap(function (_context11) {
        while (1) switch (_context11.prev = _context11.next) {
          case 0:
            headers = {};
            credentials = "include";
            if (accessToken !== undefined) {
              headers["authorization"] = "Bearer ".concat(accessToken);
              credentials = "omit";
            }
            _context11.next = 1;
            return this._fetchOIDCConfiguration();
          case 1:
            config = _context11.sent;
            _context11.next = 2;
            return this._fetchOIDCJSON(config.userinfo_endpoint, {
              method: "GET",
              headers: headers,
              mode: "cors",
              credentials: credentials
            });
          case 2:
            response = _context11.sent;
            return _context11.abrupt("return", _decodeUserInfo(response));
          case 3:
          case "end":
            return _context11.stop();
        }
      }, _callee11, this);
    }));
    function _oidcUserInfoRequest(_x18) {
      return _oidcUserInfoRequest2.apply(this, arguments);
    }
    return _oidcUserInfoRequest;
  }();
  _proto._oidcRevocationRequest = /*#__PURE__*/function () {
    var _oidcRevocationRequest2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee12(refreshToken) {
      var config, query;
      return _regeneratorRuntime.wrap(function (_context12) {
        while (1) switch (_context12.prev = _context12.next) {
          case 0:
            _context12.next = 1;
            return this._fetchOIDCConfiguration();
          case 1:
            config = _context12.sent;
            query = new URLSearchParams$2({
              token: refreshToken
            });
            _context12.next = 2;
            return this._fetchOIDCRequest(config.revocation_endpoint, {
              method: "POST",
              headers: {
                "content-type": "application/x-www-form-urlencoded"
              },
              body: query.toString()
            });
          case 2:
          case "end":
            return _context12.stop();
        }
      }, _callee12, this);
    }));
    function _oidcRevocationRequest(_x19) {
      return _oidcRevocationRequest2.apply(this, arguments);
    }
    return _oidcRevocationRequest;
  }();
  _proto._wechatAuthCallbackRequest = /*#__PURE__*/function () {
    var _wechatAuthCallbackRequest2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee13(code, state, platform) {
      var query;
      return _regeneratorRuntime.wrap(function (_context13) {
        while (1) switch (_context13.prev = _context13.next) {
          case 0:
            query = new URLSearchParams$2({
              code: code,
              state: state,
              x_platform: platform
            });
            _context13.next = 1;
            return this._request("POST", "/sso/wechat/callback", {
              credentials: "omit",
              headers: {
                "content-type": "application/x-www-form-urlencoded"
              },
              body: query.toString()
            });
          case 1:
          case "end":
            return _context13.stop();
        }
      }, _callee13, this);
    }));
    function _wechatAuthCallbackRequest(_x20, _x21, _x22) {
      return _wechatAuthCallbackRequest2.apply(this, arguments);
    }
    return _wechatAuthCallbackRequest;
  }();
  _proto.getEndpointOrigin = /*#__PURE__*/function () {
    var _getEndpointOrigin = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee14() {
      var config;
      return _regeneratorRuntime.wrap(function (_context14) {
        while (1) switch (_context14.prev = _context14.next) {
          case 0:
            _context14.next = 1;
            return this._fetchOIDCConfiguration();
          case 1:
            config = _context14.sent;
            return _context14.abrupt("return", new URL$1(config.authorization_endpoint).origin);
          case 2:
          case "end":
            return _context14.stop();
        }
      }, _callee14, this);
    }));
    function getEndpointOrigin() {
      return _getEndpointOrigin.apply(this, arguments);
    }
    return getEndpointOrigin;
  }();
  _proto.appSessionToken = /*#__PURE__*/function () {
    var _appSessionToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee15(refreshToken) {
      return _regeneratorRuntime.wrap(function (_context15) {
        while (1) switch (_context15.prev = _context15.next) {
          case 0:
            return _context15.abrupt("return", this._post("/oauth2/app_session_token", {
              credentials: "omit",
              json: {
                refresh_token: refreshToken
              }
            }));
          case 1:
          case "end":
            return _context15.stop();
        }
      }, _callee15, this);
    }));
    function appSessionToken(_x23) {
      return _appSessionToken.apply(this, arguments);
    }
    return appSessionToken;
  }();
  _proto.oauthChallenge = /*#__PURE__*/function () {
    var _oauthChallenge = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee16(purpose) {
      return _regeneratorRuntime.wrap(function (_context16) {
        while (1) switch (_context16.prev = _context16.next) {
          case 0:
            return _context16.abrupt("return", this._post("/oauth2/challenge", {
              credentials: "omit",
              json: {
                purpose: purpose
              }
            }));
          case 1:
          case "end":
            return _context16.stop();
        }
      }, _callee16, this);
    }));
    function oauthChallenge(_x24) {
      return _oauthChallenge.apply(this, arguments);
    }
    return oauthChallenge;
  }();
  _proto.signupAnonymousUserWithoutKey = /*#__PURE__*/function () {
    var _signupAnonymousUserWithoutKey = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee17(clientID, sessionType, refreshToken) {
      var payload, result;
      return _regeneratorRuntime.wrap(function (_context17) {
        while (1) switch (_context17.prev = _context17.next) {
          case 0:
            payload = {
              client_id: clientID,
              session_type: sessionType
            };
            if (refreshToken) {
              payload["refresh_token"] = refreshToken;
            }
            _context17.next = 1;
            return this._post("/api/anonymous_user/signup", {
              json: payload,
              credentials: sessionType === "cookie" ? "include" : "omit"
            });
          case 1:
            result = _context17.sent;
            if (!(Object.keys(result).length === 0)) {
              _context17.next = 2;
              break;
            }
            return _context17.abrupt("return", undefined);
          case 2:
            return _context17.abrupt("return", result);
          case 3:
          case "end":
            return _context17.stop();
        }
      }, _callee17, this);
    }));
    function signupAnonymousUserWithoutKey(_x25, _x26, _x27) {
      return _signupAnonymousUserWithoutKey.apply(this, arguments);
    }
    return signupAnonymousUserWithoutKey;
  }();
  _proto.anonymousUserPromotionCode = /*#__PURE__*/function () {
    var _anonymousUserPromotionCode = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee18(sessionType, refreshToken) {
      var payload;
      return _regeneratorRuntime.wrap(function (_context18) {
        while (1) switch (_context18.prev = _context18.next) {
          case 0:
            payload = {
              session_type: sessionType
            };
            if (refreshToken) {
              payload["refresh_token"] = refreshToken;
            }
            return _context18.abrupt("return", this._post("/api/anonymous_user/promotion_code", {
              json: payload,
              credentials: sessionType === "cookie" ? "include" : "omit"
            }));
          case 1:
          case "end":
            return _context18.stop();
        }
      }, _callee18, this);
    }));
    function anonymousUserPromotionCode(_x28, _x29) {
      return _anonymousUserPromotionCode.apply(this, arguments);
    }
    return anonymousUserPromotionCode;
  }();
  return _createClass(_BaseAPIClient, [{
    key: "endpoint",
    get: function get() {
      return this._endpoint;
    },
    set: function set(newEndpoint) {
      if (newEndpoint != null) {
        this._endpoint = _removeTrailingSlash(newEndpoint);
        // When endpoint changes, remove the cached openid configuration.
        this._config = undefined;
      } else {
        this._endpoint = undefined;
      }
    }

    // eslint-disable-next-line no-undef

    // eslint-disable-next-line no-undef
  }]);
}();

function _extends() {
  return _extends = Object.assign ? Object.assign.bind() : function (n) {
    for (var e = 1; e < arguments.length; e++) {
      var t = arguments[e];
      for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]);
    }
    return n;
  }, _extends.apply(null, arguments);
}

/*
 * base64-arraybuffer 1.0.2 <https://github.com/niklasvh/base64-arraybuffer>
 * Copyright (c) 2022 Niklas von Hertzen <https://hertzen.com>
 * Released under MIT License
 */
var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
// Use a lookup table to find the index.
var lookup = typeof Uint8Array === 'undefined' ? [] : new Uint8Array(256);
for (var i = 0; i < chars.length; i++) {
    lookup[chars.charCodeAt(i)] = i;
}
var encode = function (arraybuffer) {
    var bytes = new Uint8Array(arraybuffer), i, len = bytes.length, base64 = '';
    for (i = 0; i < len; i += 3) {
        base64 += chars[bytes[i] >> 2];
        base64 += chars[((bytes[i] & 3) << 4) | (bytes[i + 1] >> 4)];
        base64 += chars[((bytes[i + 1] & 15) << 2) | (bytes[i + 2] >> 6)];
        base64 += chars[bytes[i + 2] & 63];
    }
    if (len % 3 === 2) {
        base64 = base64.substring(0, base64.length - 1) + '=';
    }
    else if (len % 3 === 1) {
        base64 = base64.substring(0, base64.length - 2) + '==';
    }
    return base64;
};
var decode = function (base64) {
    var bufferLength = base64.length * 0.75, len = base64.length, i, p = 0, encoded1, encoded2, encoded3, encoded4;
    if (base64[base64.length - 1] === '=') {
        bufferLength--;
        if (base64[base64.length - 2] === '=') {
            bufferLength--;
        }
    }
    var arraybuffer = new ArrayBuffer(bufferLength), bytes = new Uint8Array(arraybuffer);
    for (i = 0; i < len; i += 4) {
        encoded1 = lookup[base64.charCodeAt(i)];
        encoded2 = lookup[base64.charCodeAt(i + 1)];
        encoded3 = lookup[base64.charCodeAt(i + 2)];
        encoded4 = lookup[base64.charCodeAt(i + 3)];
        bytes[p++] = (encoded1 << 2) | (encoded2 >> 4);
        bytes[p++] = ((encoded2 & 15) << 4) | (encoded3 >> 2);
        bytes[p++] = ((encoded3 & 3) << 6) | (encoded4 & 63);
    }
    return arraybuffer;
};

/**
 * @internal
 */
function _base64URLEncode(input) {
  return encode(input).replace(/\//g, "_").replace(/\+/g, "-").replace(/=+$/, "");
}

/**
 * @internal
 */
function _base64URLDecode(s) {
  // base64-arraybuffer ignores padding actually.
  // https://github.com/niklasvh/base64-arraybuffer/blob/master/lib/base64-arraybuffer.js#L44
  // so we need not bother with adding padding to the encoded string.
  return decode(s.replace(/-/g, "+").replace(/_/g, "/"));
}

var textEncodingShim = {exports: {}};

(function (module, exports$1) {
	(function (root, factory) {
	    {
	        module.exports = factory();
	    }
	}(commonjsGlobal, function () {
		// return native implementation if available
		var g = typeof commonjsGlobal !== 'undefined' ? commonjsGlobal : self;
		if (typeof g.TextEncoder !== 'undefined' && typeof g.TextDecoder !== 'undefined') {
			return {'TextEncoder': g.TextEncoder, 'TextDecoder': g.TextDecoder};
		}

		// allowed encoding strings for utf-8
		var utf8Encodings = [
			'utf8',
			'utf-8',
			'unicode-1-1-utf-8'
		];

		var TextEncoder = function(encoding) {
			if (utf8Encodings.indexOf(encoding) < 0 && typeof encoding !== 'undefined' && encoding !== null) {
				throw new RangeError('Invalid encoding type. Only utf-8 is supported');
			} else {
				this.encoding = 'utf-8';
				this.encode = function(str) {
					if (typeof str !== 'string') {
						throw new TypeError('passed argument must be of type string');
					}
					var binstr = unescape(encodeURIComponent(str)),
						arr = new Uint8Array(binstr.length);
					binstr.split('').forEach(function(char, i) {
						arr[i] = char.charCodeAt(0);
					});
					return arr;
				};
			}
		};

		var TextDecoder = function(encoding, options) {
			if (utf8Encodings.indexOf(encoding) < 0 && typeof encoding !== 'undefined' && encoding !== null) {
				throw new RangeError('Invalid encoding type. Only utf-8 is supported');
			}
			this.encoding = 'utf-8';
			this.ignoreBOM = false;
			this.fatal = (typeof options !== 'undefined' && 'fatal' in options) ? options.fatal : false;
			if (typeof this.fatal !== 'boolean') {
				throw new TypeError('fatal flag must be boolean');
			}
			this.decode = function (view, options) {
				if (typeof view === 'undefined') {
					return '';
				}

				var stream = (typeof options !== 'undefined' && 'stream' in options) ? options.stream : false;
				if (typeof stream !== 'boolean') {
					throw new TypeError('stream option must be boolean');
				}

				if (!ArrayBuffer.isView(view)) {
					throw new TypeError('passed argument must be an array buffer view');
				} else {
					var arr = new Uint8Array(view.buffer, view.byteOffset, view.byteLength),
						charArr = new Array(arr.length);
					arr.forEach(function(charcode, i) {
						charArr[i] = String.fromCharCode(charcode);
					});
					return decodeURIComponent(escape(charArr.join('')));
				}
			};
		};
		return {'TextEncoder': TextEncoder, 'TextDecoder': TextDecoder};
	})); 
} (textEncodingShim));

var textEncodingShimExports = textEncodingShim.exports;

/**
 * @internal
 */
function _encodeUTF8(input) {
  return new textEncodingShimExports.TextEncoder().encode(input);
}

/**
 * @internal
 */
function _decodeUTF8(input) {
  return new textEncodingShimExports.TextDecoder().decode(input);
}

/**
 * To prevent user from using expired access token, we have to check in advance
 * whether it had expired in `shouldRefreshAccessToken`. If we
 * use the expiry time in {@link _OIDCTokenResponse} directly to check for expiry, it is possible
 * that the access token had passed the check but ends up being expired when it arrives at
 * the server due to slow traffic or unfair scheduler.
 *
 * To compat this, we should consider the access token expired earlier than the expiry time
 * calculated using {@link _OIDCTokenResponse.expires_in}. Current implementation uses
 * {@link EXPIRE_IN_PERCENTAGE} of {@link _OIDCTokenResponse.expires_in} to calculate the expiry time.
 *
 * @internal
 */
var EXPIRE_IN_PERCENTAGE = 0.9;

/**
 * @internal
 */

/**
 * @internal
 */
function _decodeIDToken(idToken) {
  // idToken is the format
  // base64URLEncode(header) "." base64URLEncode(payload) "." signature
  if (idToken == null) {
    return undefined;
  }
  var parts = idToken.split(".");
  if (parts.length !== 3) {
    return undefined;
  }
  var payload = parts[1];
  var utf8Bytes = _base64URLDecode(payload);
  var utf8Str = _decodeUTF8(new Uint8Array(utf8Bytes));
  var idTokenPayload = JSON.parse(utf8Str);
  return idTokenPayload;
}

/**
 * @internal
 */
function _canReauthenticate(idTokenPayload) {
  var can = idTokenPayload["https://authgear.com/claims/user/can_reauthenticate"];
  if (typeof can === "boolean") {
    return can;
  }
  return false;
}

/**
 * @internal
 */
function _getAuthTime(idTokenPayload) {
  var authTimeValue = idTokenPayload["auth_time"];
  if (typeof authTimeValue === "number") {
    // authTimeValue is Unix epoch while JavaScript Date constructor accepts milliseconds.
    return new Date(authTimeValue * 1000);
  }
  return undefined;
}

/**
 * Base Container
 *
 * @internal
 */
var _BaseContainer = /*#__PURE__*/function () {
  function _BaseContainer(options, apiClient, _delegate) {
    var _options$name;
    this.refreshAccessTokenTask = null;
    this.name = (_options$name = options.name) != null ? _options$name : "default";
    this.apiClient = apiClient;
    this.isSSOEnabled = false;
    this.preAuthenticatedURLEnabled = false;
    this.sessionState = SessionState.Unknown;
    this._delegate = _delegate;
  }
  var _proto = _BaseContainer.prototype;
  _proto.getIDTokenHint = function getIDTokenHint() {
    return this.idToken;
  };
  _proto.canReauthenticate = function canReauthenticate() {
    var payload = _decodeIDToken(this.idToken);
    if (payload == null) {
      return false;
    }
    return _canReauthenticate(payload);
  };
  _proto.getAuthTime = function getAuthTime() {
    var payload = _decodeIDToken(this.idToken);
    if (payload == null) {
      return undefined;
    }
    return _getAuthTime(payload);
  };
  _proto.getAuthenticateScopes = function getAuthenticateScopes(options) {
    var requestOfflineAccess = options.requestOfflineAccess;
    var scopes = ["openid", "https://authgear.com/scopes/full-access"];
    if (requestOfflineAccess) {
      scopes.push("offline_access");
    }
    if (this.preAuthenticatedURLEnabled) {
      scopes.push("device_sso", "https://authgear.com/scopes/pre-authenticated-url");
    }
    return scopes;
  }

  // eslint-disable-next-line class-methods-use-this
;
  _proto.getReauthenticateScopes = function getReauthenticateScopes() {
    // offline_access is not needed because we don't want a new refresh token to be generated
    // device_sso and pre-authenticated-url is also not needed,
    // because no new session should be generated so the scopes are not important.
    return ["openid", "https://authgear.com/scopes/full-access"];
  }

  // eslint-disable-next-line class-methods-use-this
;
  _proto.getSettingsActionScopes = function getSettingsActionScopes() {
    // offline_access is not needed because we don't want a new refresh token to be generated
    // device_sso and pre-authenticated-url is also not needed,
    // because session for settings should not be used to perform SSO.
    return ["openid", "https://authgear.com/scopes/full-access"];
  };
  _proto._persistTokenResponse = /*#__PURE__*/function () {
    var _persistTokenResponse2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee(response, reason) {
      var id_token, access_token, refresh_token, expires_in, device_secret;
      return _regeneratorRuntime.wrap(function (_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            id_token = response.id_token, access_token = response.access_token, refresh_token = response.refresh_token, expires_in = response.expires_in, device_secret = response.device_secret;
            if (!(access_token == null || expires_in == null)) {
              _context.next = 1;
              break;
            }
            throw new AuthgearError("access_token or expires_in missing in Token Response");
          case 1:
            if (!(id_token != null)) {
              _context.next = 2;
              break;
            }
            this.idToken = id_token;
            _context.next = 2;
            return this._delegate.sharedStorage.setIDToken(this.name, id_token);
          case 2:
            this.accessToken = access_token;
            if (refresh_token) {
              this.refreshToken = refresh_token;
            }
            this.expireAt = new Date(new Date(Date.now()).getTime() + expires_in * EXPIRE_IN_PERCENTAGE * 1000);
            this._updateSessionState(SessionState.Authenticated, reason);
            if (!refresh_token) {
              _context.next = 3;
              break;
            }
            _context.next = 3;
            return this._delegate.tokenStorage.setRefreshToken(this.name, refresh_token);
          case 3:
            if (!(device_secret != null)) {
              _context.next = 4;
              break;
            }
            _context.next = 4;
            return this._delegate.sharedStorage.setDeviceSecret(this.name, device_secret);
          case 4:
          case "end":
            return _context.stop();
        }
      }, _callee, this);
    }));
    function _persistTokenResponse(_x, _x2) {
      return _persistTokenResponse2.apply(this, arguments);
    }
    return _persistTokenResponse;
  }();
  _proto._clearSession = /*#__PURE__*/function () {
    var _clearSession2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2(reason) {
      return _regeneratorRuntime.wrap(function (_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            _context2.next = 1;
            return this._delegate.tokenStorage.delRefreshToken(this.name);
          case 1:
            _context2.next = 2;
            return this._delegate.sharedStorage.onLogout(this.name);
          case 2:
            this.idToken = undefined;
            this.accessToken = undefined;
            this.refreshToken = undefined;
            this.expireAt = undefined;
            this._updateSessionState(SessionState.NoSession, reason);
          case 3:
          case "end":
            return _context2.stop();
        }
      }, _callee2, this);
    }));
    function _clearSession(_x3) {
      return _clearSession2.apply(this, arguments);
    }
    return _clearSession;
  }();
  _proto._handleInvalidGrantError = /*#__PURE__*/function () {
    var _handleInvalidGrantError2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee3(error) {
      return _regeneratorRuntime.wrap(function (_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            if (!(error != null)) {
              _context3.next = 3;
              break;
            }
            if (!(error.error === "invalid_grant")) {
              _context3.next = 2;
              break;
            }
            _context3.next = 1;
            return this._clearSession(SessionStateChangeReason.Invalid);
          case 1:
            _context3.next = 3;
            break;
          case 2:
            if (!(error.reason === "InvalidGrant")) {
              _context3.next = 3;
              break;
            }
            _context3.next = 3;
            return this._clearSession(SessionStateChangeReason.Invalid);
          case 3:
          case "end":
            return _context3.stop();
        }
      }, _callee3, this);
    }));
    function _handleInvalidGrantError(_x4) {
      return _handleInvalidGrantError2.apply(this, arguments);
    }
    return _handleInvalidGrantError;
  }();
  _proto.refreshAccessTokenIfNeeded = /*#__PURE__*/function () {
    var _refreshAccessTokenIfNeeded = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee5() {
      var _this = this;
      var task;
      return _regeneratorRuntime.wrap(function (_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            if (!(this.refreshAccessTokenTask !== null)) {
              _context5.next = 1;
              break;
            }
            return _context5.abrupt("return", this.refreshAccessTokenTask);
          case 1:
            task = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee4() {
              return _regeneratorRuntime.wrap(function (_context4) {
                while (1) switch (_context4.prev = _context4.next) {
                  case 0:
                    if (!_this.shouldRefreshAccessToken()) {
                      _context4.next = 1;
                      break;
                    }
                    _context4.next = 1;
                    return _this._delegate.refreshAccessToken();
                  case 1:
                  case "end":
                    return _context4.stop();
                }
              }, _callee4);
            }))().finally(function () {
              if (task === _this.refreshAccessTokenTask) {
                _this.refreshAccessTokenTask = null;
              }
            });
            this.refreshAccessTokenTask = task;
            return _context5.abrupt("return", task);
          case 2:
          case "end":
            return _context5.stop();
        }
      }, _callee5, this);
    }));
    function refreshAccessTokenIfNeeded() {
      return _refreshAccessTokenIfNeeded.apply(this, arguments);
    }
    return refreshAccessTokenIfNeeded;
  }();
  _proto.clearSessionState = /*#__PURE__*/function () {
    var _clearSessionState = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee6() {
      return _regeneratorRuntime.wrap(function (_context6) {
        while (1) switch (_context6.prev = _context6.next) {
          case 0:
            _context6.next = 1;
            return this._clearSession(SessionStateChangeReason.Clear);
          case 1:
          case "end":
            return _context6.stop();
        }
      }, _callee6, this);
    }));
    function clearSessionState() {
      return _clearSessionState.apply(this, arguments);
    }
    return clearSessionState;
  }();
  _proto.shouldRefreshAccessToken = function shouldRefreshAccessToken() {
    // No need to refresh if we do not even have a refresh token.
    if (this.refreshToken == null) {
      return false;
    }

    // When we have a refresh token but not an access token
    if (this.accessToken == null) {
      return true;
    }

    // When we have a refresh token and an access token but its expiration is unknown.
    if (this.expireAt == null) {
      return true;
    }

    // When we have a refresh token and an access token but it is indeed expired.
    var now = new Date(Date.now());
    if (this.expireAt.getTime() < now.getTime()) {
      return true;
    }

    // Otherwise no need to refresh.
    return false;
  };
  _proto.fetch = /*#__PURE__*/function () {
    var _fetch = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee7(input, init) {
      return _regeneratorRuntime.wrap(function (_context7) {
        while (1) switch (_context7.prev = _context7.next) {
          case 0:
            return _context7.abrupt("return", this.apiClient.fetch(input, init));
          case 1:
          case "end":
            return _context7.stop();
        }
      }, _callee7, this);
    }));
    function fetch(_x5, _x6) {
      return _fetch.apply(this, arguments);
    }
    return fetch;
  }();
  _proto._refreshAccessToken = /*#__PURE__*/function () {
    var _refreshAccessToken2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee8(tokenRequest) {
      var clientID, refreshToken, deviceSecret, request, tokenResponse, _t;
      return _regeneratorRuntime.wrap(function (_context8) {
        while (1) switch (_context8.prev = _context8.next) {
          case 0:
            // If token request fails due to other reasons, session will be kept and
            // the whole process can be retried.
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context8.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            _context8.next = 2;
            return this._delegate.tokenStorage.getRefreshToken(this.name);
          case 2:
            refreshToken = _context8.sent;
            if (!(refreshToken == null)) {
              _context8.next = 4;
              break;
            }
            _context8.next = 3;
            return this._clearSession(SessionStateChangeReason.NoToken);
          case 3:
            return _context8.abrupt("return");
          case 4:
            _context8.next = 5;
            return this._delegate.sharedStorage.getDeviceSecret(this.name);
          case 5:
            deviceSecret = _context8.sent;
            request = _extends({}, tokenRequest, {
              grant_type: "refresh_token",
              client_id: clientID,
              refresh_token: refreshToken
            });
            if (deviceSecret) {
              request.device_secret = deviceSecret;
            }
            _context8.prev = 6;
            _context8.next = 7;
            return this.apiClient._oidcTokenRequest(request);
          case 7:
            tokenResponse = _context8.sent;
            _context8.next = 11;
            break;
          case 8:
            _context8.prev = 8;
            _t = _context8["catch"](6);
            _context8.next = 9;
            return this._handleInvalidGrantError(_t);
          case 9:
            if (!(_t != null && _t.error === "invalid_grant")) {
              _context8.next = 10;
              break;
            }
            return _context8.abrupt("return");
          case 10:
            throw _t;
          case 11:
            _context8.next = 12;
            return this._persistTokenResponse(tokenResponse, SessionStateChangeReason.FoundToken);
          case 12:
          case "end":
            return _context8.stop();
        }
      }, _callee8, this, [[6, 8]]);
    }));
    function _refreshAccessToken(_x7) {
      return _refreshAccessToken2.apply(this, arguments);
    }
    return _refreshAccessToken;
  }();
  _proto.refreshIDToken = /*#__PURE__*/function () {
    var _refreshIDToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee9() {
      var clientID, deviceSecret, accessToken, tokenRequest, _yield$this$apiClient, id_token, device_secret, _t2;
      return _regeneratorRuntime.wrap(function (_context9) {
        while (1) switch (_context9.prev = _context9.next) {
          case 0:
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context9.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            _context9.next = 2;
            return this._delegate.sharedStorage.getDeviceSecret(this.name);
          case 2:
            deviceSecret = _context9.sent;
            _context9.next = 3;
            return this.refreshAccessTokenIfNeeded();
          case 3:
            accessToken = this.accessToken;
            tokenRequest = {
              grant_type: "urn:authgear:params:oauth:grant-type:id-token",
              client_id: clientID,
              access_token: accessToken
            };
            if (deviceSecret) {
              tokenRequest.device_secret = deviceSecret;
            }
            _context9.prev = 4;
            _context9.next = 5;
            return this.apiClient._oidcTokenRequest(tokenRequest);
          case 5:
            _yield$this$apiClient = _context9.sent;
            id_token = _yield$this$apiClient.id_token;
            device_secret = _yield$this$apiClient.device_secret;
            if (!(id_token != null)) {
              _context9.next = 6;
              break;
            }
            this.idToken = id_token;
            _context9.next = 6;
            return this._delegate.sharedStorage.setIDToken(this.name, id_token);
          case 6:
            if (!(device_secret != null)) {
              _context9.next = 7;
              break;
            }
            _context9.next = 7;
            return this._delegate.sharedStorage.setDeviceSecret(this.name, device_secret);
          case 7:
            _context9.next = 10;
            break;
          case 8:
            _context9.prev = 8;
            _t2 = _context9["catch"](4);
            _context9.next = 9;
            return this._handleInvalidGrantError(_t2);
          case 9:
            throw _t2;
          case 10:
          case "end":
            return _context9.stop();
        }
      }, _callee9, this, [[4, 8]]);
    }));
    function refreshIDToken() {
      return _refreshIDToken.apply(this, arguments);
    }
    return refreshIDToken;
  }();
  _proto.getAuthorizationEndpoint = /*#__PURE__*/function () {
    var _getAuthorizationEndpoint = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee0() {
      var config;
      return _regeneratorRuntime.wrap(function (_context0) {
        while (1) switch (_context0.prev = _context0.next) {
          case 0:
            _context0.next = 1;
            return this.apiClient._fetchOIDCConfiguration();
          case 1:
            config = _context0.sent;
            return _context0.abrupt("return", new URL$1(config.authorization_endpoint));
          case 2:
          case "end":
            return _context0.stop();
        }
      }, _callee0, this);
    }));
    function getAuthorizationEndpoint() {
      return _getAuthorizationEndpoint.apply(this, arguments);
    }
    return getAuthorizationEndpoint;
  }() // eslint-disable-next-line complexity
;
  _proto.authorizeEndpoint =
  /*#__PURE__*/
  function () {
    var _authorizeEndpoint = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee1(options) {
      var _options$clientID;
      var clientID, config, query, responseType, codeVerifier;
      return _regeneratorRuntime.wrap(function (_context1) {
        while (1) switch (_context1.prev = _context1.next) {
          case 0:
            clientID = (_options$clientID = options.clientID) != null ? _options$clientID : this.clientID;
            if (!(clientID == null)) {
              _context1.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            _context1.next = 2;
            return this.apiClient._fetchOIDCConfiguration();
          case 2:
            config = _context1.sent;
            query = new URLSearchParams$2();
            responseType = options.responseType;
            query.append("response_type", responseType);
            if (!(responseType === "code" || responseType === "urn:authgear:params:oauth:response-type:settings-action")) {
              _context1.next = 5;
              break;
            }
            _context1.next = 3;
            return this._delegate._setupCodeVerifier();
          case 3:
            codeVerifier = _context1.sent;
            _context1.next = 4;
            return this._delegate.storage.setOIDCCodeVerifier(this.name, codeVerifier.verifier);
          case 4:
            query.append("code_challenge_method", "S256");
            query.append("code_challenge", codeVerifier.challenge);
          case 5:
            if (options.responseMode != null) {
              query.append("response_mode", options.responseMode);
            }
            if (options.scope != null) {
              query.append("scope", options.scope.join(" "));
            }
            query.append("client_id", clientID);
            query.append("redirect_uri", options.redirectURI);
            if (options.state != null) {
              query.append("state", options.state);
            }
            if (options.xState != null) {
              query.append("x_state", options.xState);
            }
            if (options.prompt != null) {
              if (typeof options.prompt === "string") {
                query.append("prompt", options.prompt);
              } else if (options.prompt.length > 0) {
                query.append("prompt", options.prompt.join(" "));
              }
            }
            if (options.loginHint != null) {
              query.append("login_hint", options.loginHint);
            }
            if (options.uiLocales != null) {
              query.append("ui_locales", options.uiLocales.join(" "));
            }
            if (options.colorScheme != null) {
              query.append("x_color_scheme", options.colorScheme);
            }
            if (options.idTokenHint != null) {
              query.append("id_token_hint", options.idTokenHint);
            }
            if (options.maxAge != null) {
              query.append("max_age", String(options.maxAge));
            }
            if (options.wechatRedirectURI != null) {
              query.append("x_wechat_redirect_uri", options.wechatRedirectURI);
            }
            if (options.platform != null) {
              query.append("x_platform", options.platform);
            }
            if (options.page != null) {
              query.append("x_page", options.page);
            }
            if (options.oauthProviderAlias != null) {
              query.append("x_oauth_provider_alias", options.oauthProviderAlias);
            }
            if (options.xSettingsAction != null) {
              query.append("x_settings_action", options.xSettingsAction);
            }
            if (options.xSettingsActionQuery != null) {
              query.append("x_settings_action_query", new URLSearchParams$2(Object.entries(options.xSettingsActionQuery)).toString());
            }
            if (options.xPreAuthenticatedURLToken != null) {
              query.append("x_pre_authenticated_url_token", options.xPreAuthenticatedURLToken);
            }
            if (options.dpopJKT != null) {
              query.append("dpop_jkt", options.dpopJKT);
            }
            if (!this.isSSOEnabled) {
              // For backward compatibility
              // If the developer updates the SDK but not the server
              query.append("x_suppress_idp_session_cookie", "true");
            }
            query.append("x_sso_enabled", this.isSSOEnabled ? "true" : "false");
            if (options.authenticationFlowGroup != null) {
              query.append("x_authentication_flow_group", options.authenticationFlowGroup);
            }
            return _context1.abrupt("return", "".concat(config.authorization_endpoint, "?").concat(query.toString()));
          case 6:
          case "end":
            return _context1.stop();
        }
      }, _callee1, this);
    }));
    function authorizeEndpoint(_x8) {
      return _authorizeEndpoint.apply(this, arguments);
    }
    return authorizeEndpoint;
  }();
  _proto._finishAuthentication = /*#__PURE__*/function () {
    var _finishAuthentication2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee10(url, codeRequired, tokenRequest) {
      var _params$get2;
      var clientID, u, params, uu, redirectURI, _params$get, userInfo, tokenResponse, code, codeVerifier;
      return _regeneratorRuntime.wrap(function (_context10) {
        while (1) switch (_context10.prev = _context10.next) {
          case 0:
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context10.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            u = new URL$1(url);
            params = u.searchParams;
            uu = new URL$1(url);
            uu.hash = "";
            uu.search = "";
            redirectURI = uu.toString();
            if (!params.get("error")) {
              _context10.next = 2;
              break;
            }
            throw new OAuthError({
              error: params.get("error"),
              error_description: (_params$get = params.get("error_description")) != null ? _params$get : undefined
            });
          case 2:
            if (codeRequired) {
              _context10.next = 4;
              break;
            }
            _context10.next = 3;
            return this.apiClient._oidcUserInfoRequest();
          case 3:
            userInfo = _context10.sent;
            _context10.next = 9;
            break;
          case 4:
            code = params.get("code");
            if (code) {
              _context10.next = 5;
              break;
            }
            throw new OAuthError({
              error: "invalid_request",
              error_description: "Missing parameter: code"
            });
          case 5:
            _context10.next = 6;
            return this._delegate.storage.getOIDCCodeVerifier(this.name);
          case 6:
            codeVerifier = _context10.sent;
            _context10.next = 7;
            return this.apiClient._oidcTokenRequest(_extends({}, tokenRequest, {
              grant_type: "authorization_code",
              code: code,
              redirect_uri: redirectURI,
              client_id: clientID,
              code_verifier: codeVerifier != null ? codeVerifier : ""
            }));
          case 7:
            tokenResponse = _context10.sent;
            _context10.next = 8;
            return this.apiClient._oidcUserInfoRequest(tokenResponse.access_token);
          case 8:
            userInfo = _context10.sent;
          case 9:
            if (!tokenResponse) {
              _context10.next = 10;
              break;
            }
            _context10.next = 10;
            return this._persistTokenResponse(tokenResponse, SessionStateChangeReason.Authenticated);
          case 10:
            return _context10.abrupt("return", {
              userInfo: userInfo,
              state: (_params$get2 = params.get("state")) != null ? _params$get2 : undefined
            });
          case 11:
          case "end":
            return _context10.stop();
        }
      }, _callee10, this);
    }));
    function _finishAuthentication(_x9, _x0, _x1) {
      return _finishAuthentication2.apply(this, arguments);
    }
    return _finishAuthentication;
  }();
  _proto._finishReauthentication = /*#__PURE__*/function () {
    var _finishReauthentication2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee11(url, tokenRequest) {
      var _params$get4;
      var clientID, u, params, uu, redirectURI, _params$get3, code, codeVerifier, _yield$this$apiClient2, id_token, access_token, userInfo;
      return _regeneratorRuntime.wrap(function (_context11) {
        while (1) switch (_context11.prev = _context11.next) {
          case 0:
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context11.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            u = new URL$1(url);
            params = u.searchParams;
            uu = new URL$1(url);
            uu.hash = "";
            uu.search = "";
            redirectURI = uu.toString();
            if (!params.get("error")) {
              _context11.next = 2;
              break;
            }
            throw new OAuthError({
              error: params.get("error"),
              error_description: (_params$get3 = params.get("error_description")) != null ? _params$get3 : undefined
            });
          case 2:
            code = params.get("code");
            if (code) {
              _context11.next = 3;
              break;
            }
            throw new OAuthError({
              error: "invalid_request",
              error_description: "Missing parameter: code"
            });
          case 3:
            _context11.next = 4;
            return this._delegate.storage.getOIDCCodeVerifier(this.name);
          case 4:
            codeVerifier = _context11.sent;
            _context11.next = 5;
            return this.apiClient._oidcTokenRequest(_extends({}, tokenRequest, {
              grant_type: "authorization_code",
              code: code,
              redirect_uri: redirectURI,
              client_id: clientID,
              code_verifier: codeVerifier != null ? codeVerifier : ""
            }));
          case 5:
            _yield$this$apiClient2 = _context11.sent;
            id_token = _yield$this$apiClient2.id_token;
            access_token = _yield$this$apiClient2.access_token;
            _context11.next = 6;
            return this.apiClient._oidcUserInfoRequest(access_token);
          case 6:
            userInfo = _context11.sent;
            if (id_token != null) {
              this.idToken = id_token;
            }
            return _context11.abrupt("return", {
              userInfo: userInfo,
              state: (_params$get4 = params.get("state")) != null ? _params$get4 : undefined
            });
          case 7:
          case "end":
            return _context11.stop();
        }
      }, _callee11, this);
    }));
    function _finishReauthentication(_x10, _x11) {
      return _finishReauthentication2.apply(this, arguments);
    }
    return _finishReauthentication;
  }();
  _proto._finishSettingsAction = /*#__PURE__*/function () {
    var _finishSettingsAction2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee12(url, tokenRequest) {
      var clientID, u, params, uu, redirectURI, _params$get5, code, codeVerifier;
      return _regeneratorRuntime.wrap(function (_context12) {
        while (1) switch (_context12.prev = _context12.next) {
          case 0:
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context12.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            u = new URL$1(url);
            params = u.searchParams;
            uu = new URL$1(url);
            uu.hash = "";
            uu.search = "";
            redirectURI = uu.toString();
            if (!params.get("error")) {
              _context12.next = 2;
              break;
            }
            throw new OAuthError({
              error: params.get("error"),
              error_description: (_params$get5 = params.get("error_description")) != null ? _params$get5 : undefined
            });
          case 2:
            code = params.get("code");
            if (code) {
              _context12.next = 3;
              break;
            }
            throw new OAuthError({
              error: "invalid_request",
              error_description: "Missing parameter: code"
            });
          case 3:
            _context12.next = 4;
            return this._delegate.storage.getOIDCCodeVerifier(this.name);
          case 4:
            codeVerifier = _context12.sent;
            _context12.next = 5;
            return this.apiClient._oidcTokenRequest(_extends({}, tokenRequest, {
              grant_type: "urn:authgear:params:oauth:grant-type:settings-action",
              code: code,
              redirect_uri: redirectURI,
              client_id: clientID,
              code_verifier: codeVerifier != null ? codeVerifier : ""
            }));
          case 5:
          case "end":
            return _context12.stop();
        }
      }, _callee12, this);
    }));
    function _finishSettingsAction(_x12, _x13) {
      return _finishSettingsAction2.apply(this, arguments);
    }
    return _finishSettingsAction;
  }()
  /**
   * Update session state.
   */
;
  _proto._updateSessionState = function _updateSessionState(state, reason) {
    this.sessionState = state;
    this._delegate.onSessionStateChange(reason);
  };
  _proto._fetchUserInfo = /*#__PURE__*/function () {
    var _fetchUserInfo2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee13(accessToken) {
      var _t3;
      return _regeneratorRuntime.wrap(function (_context13) {
        while (1) switch (_context13.prev = _context13.next) {
          case 0:
            _context13.prev = 0;
            _context13.next = 1;
            return this.apiClient._oidcUserInfoRequest(accessToken);
          case 1:
            return _context13.abrupt("return", _context13.sent);
          case 2:
            _context13.prev = 2;
            _t3 = _context13["catch"](0);
            _context13.next = 3;
            return this._handleInvalidGrantError(_t3);
          case 3:
            throw _t3;
          case 4:
          case "end":
            return _context13.stop();
        }
      }, _callee13, this, [[0, 2]]);
    }));
    function _fetchUserInfo(_x14) {
      return _fetchUserInfo2.apply(this, arguments);
    }
    return _fetchUserInfo;
  }();
  _proto._getAppSessionToken = /*#__PURE__*/function () {
    var _getAppSessionToken2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee14(refreshToken) {
      var _yield$this$apiClient3, app_session_token, _t4;
      return _regeneratorRuntime.wrap(function (_context14) {
        while (1) switch (_context14.prev = _context14.next) {
          case 0:
            _context14.prev = 0;
            _context14.next = 1;
            return this.apiClient.appSessionToken(refreshToken);
          case 1:
            _yield$this$apiClient3 = _context14.sent;
            app_session_token = _yield$this$apiClient3.app_session_token;
            return _context14.abrupt("return", app_session_token);
          case 2:
            _context14.prev = 2;
            _t4 = _context14["catch"](0);
            _context14.next = 3;
            return this._handleInvalidGrantError(_t4);
          case 3:
            throw _t4;
          case 4:
          case "end":
            return _context14.stop();
        }
      }, _callee14, this, [[0, 2]]);
    }));
    function _getAppSessionToken(_x15) {
      return _getAppSessionToken2.apply(this, arguments);
    }
    return _getAppSessionToken;
  }();
  _proto._exchangeForPreAuthenticatedURLToken = /*#__PURE__*/function () {
    var _exchangeForPreAuthenticatedURLToken2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee15(options) {
      var clientID, idToken, deviceSecret, audience, tokenExchangeResult, _t5;
      return _regeneratorRuntime.wrap(function (_context15) {
        while (1) switch (_context15.prev = _context15.next) {
          case 0:
            clientID = options.clientID, idToken = options.idToken, deviceSecret = options.deviceSecret;
            _context15.prev = 1;
            _context15.next = 2;
            return this.apiClient.getEndpointOrigin();
          case 2:
            audience = _context15.sent;
            _context15.next = 3;
            return this.apiClient._oidcTokenRequest({
              client_id: clientID,
              grant_type: "urn:ietf:params:oauth:grant-type:token-exchange",
              requested_token_type: "urn:authgear:params:oauth:token-type:pre-authenticated-url-token",
              audience: audience,
              subject_token_type: "urn:ietf:params:oauth:token-type:id_token",
              subject_token: idToken,
              actor_token_type: "urn:x-oath:params:oauth:token-type:device-secret",
              actor_token: deviceSecret
            });
          case 3:
            tokenExchangeResult = _context15.sent;
            return _context15.abrupt("return", tokenExchangeResult);
          case 4:
            _context15.prev = 4;
            _t5 = _context15["catch"](1);
            if (!(_t5 instanceof OAuthError && _t5.error === "insufficient_scope")) {
              _context15.next = 5;
              break;
            }
            throw new PreAuthenticatedURLInsufficientScopeError();
          case 5:
            throw _t5;
          case 6:
          case "end":
            return _context15.stop();
        }
      }, _callee15, this, [[1, 4]]);
    }));
    function _exchangeForPreAuthenticatedURLToken(_x16) {
      return _exchangeForPreAuthenticatedURLToken2.apply(this, arguments);
    }
    return _exchangeForPreAuthenticatedURLToken;
  }();
  _proto._makePreAuthenticatedURL = /*#__PURE__*/function () {
    var _makePreAuthenticatedURL2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee16(options) {
      var clientID, idToken, deviceSecret, tokenExchangeResult, preAuthenticatedURLToken, newDeviceSecret, newIDToken, url;
      return _regeneratorRuntime.wrap(function (_context16) {
        while (1) switch (_context16.prev = _context16.next) {
          case 0:
            clientID = options.webApplicationClientID;
            if (this.preAuthenticatedURLEnabled) {
              _context16.next = 1;
              break;
            }
            throw new AuthgearError("makePreAuthenticatedURL requires preAuthenticatedURLEnabled to be true");
          case 1:
            if (!(this.sessionState !== SessionState.Authenticated)) {
              _context16.next = 2;
              break;
            }
            throw new AuthgearError("makePreAuthenticatedURL requires authenticated user");
          case 2:
            _context16.next = 3;
            return this._delegate.sharedStorage.getIDToken(this.name);
          case 3:
            idToken = _context16.sent;
            if (idToken) {
              _context16.next = 4;
              break;
            }
            throw new PreAuthenticatedURLIDTokenNotFoundError();
          case 4:
            _context16.next = 5;
            return this._delegate.sharedStorage.getDeviceSecret(this.name);
          case 5:
            deviceSecret = _context16.sent;
            if (deviceSecret) {
              _context16.next = 6;
              break;
            }
            throw new PreAuthenticatedURLDeviceSecretNotFoundError();
          case 6:
            _context16.next = 7;
            return this._exchangeForPreAuthenticatedURLToken({
              deviceSecret: deviceSecret,
              idToken: idToken,
              clientID: clientID
            });
          case 7:
            tokenExchangeResult = _context16.sent;
            // Here access_token is pre-authenticated-url-token
            preAuthenticatedURLToken = tokenExchangeResult.access_token;
            newDeviceSecret = tokenExchangeResult.device_secret;
            newIDToken = tokenExchangeResult.id_token;
            if (!(preAuthenticatedURLToken == null)) {
              _context16.next = 8;
              break;
            }
            throw new AuthgearError("unexpected: access_token is not returned");
          case 8:
            if (!(newDeviceSecret != null)) {
              _context16.next = 9;
              break;
            }
            _context16.next = 9;
            return this._delegate.sharedStorage.setDeviceSecret(this.name, newDeviceSecret);
          case 9:
            if (!(newIDToken != null)) {
              _context16.next = 10;
              break;
            }
            idToken = newIDToken;
            this.idToken = newIDToken;
            _context16.next = 10;
            return this._delegate.sharedStorage.setIDToken(this.name, newIDToken);
          case 10:
            _context16.next = 11;
            return this.authorizeEndpoint({
              responseType: "urn:authgear:params:oauth:response-type:pre-authenticated-url token",
              responseMode: "cookie",
              redirectURI: options.webApplicationURI,
              clientID: options.webApplicationClientID,
              xPreAuthenticatedURLToken: preAuthenticatedURLToken,
              idTokenHint: idToken,
              prompt: PromptOption.None,
              state: options.state
            });
          case 11:
            url = _context16.sent;
            return _context16.abrupt("return", url);
          case 12:
          case "end":
            return _context16.stop();
        }
      }, _callee16, this);
    }));
    function _makePreAuthenticatedURL(_x17) {
      return _makePreAuthenticatedURL2.apply(this, arguments);
    }
    return _makePreAuthenticatedURL;
  }();
  return _createClass(_BaseContainer);
}();

/**
 * @internal
 */
var _KeyMaker = /*#__PURE__*/function () {
  function _KeyMaker() {}
  var _proto = _KeyMaker.prototype;
  // eslint-disable-next-line class-methods-use-this
  _proto.scopedKey = function scopedKey(key) {
    return "authgear_".concat(key);
  };
  _proto.keyRefreshToken = function keyRefreshToken(name) {
    return "".concat(this.scopedKey(name), "_refreshToken");
  };
  _proto.keyIDToken = function keyIDToken(name) {
    return "".concat(this.scopedKey(name), "_idToken");
  };
  _proto.keyDeviceSecret = function keyDeviceSecret(name) {
    return "".concat(this.scopedKey(name), "_deviceSecret");
  };
  _proto.keyOIDCCodeVerifier = function keyOIDCCodeVerifier(name) {
    return "".concat(this.scopedKey(name), "_oidcCodeVerifier");
  };
  _proto.keyAnonymousKeyID = function keyAnonymousKeyID(name) {
    return "".concat(this.scopedKey(name), "_anonymousKeyID");
  };
  _proto.keyBiometricKeyID = function keyBiometricKeyID(name) {
    return "".concat(this.scopedKey(name), "_biometricKeyID");
  };
  _proto.keyDPoPKeyID = function keyDPoPKeyID(name) {
    return "".concat(this.scopedKey(name), "_dpopKeyID");
  };
  return _createClass(_KeyMaker);
}();

/**
 * @internal
 */
var _SafeStorageDriver = /*#__PURE__*/function () {
  function _SafeStorageDriver(driver) {
    this.driver = driver;
  }
  var _proto2 = _SafeStorageDriver.prototype;
  _proto2.del = /*#__PURE__*/function () {
    var _del = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee(key) {
      return _regeneratorRuntime.wrap(function (_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            _context.prev = 0;
            _context.next = 1;
            return this.driver.del(key);
          case 1:
            _context.next = 3;
            break;
          case 2:
            _context.prev = 2;
            _context["catch"](0);
          case 3:
          case "end":
            return _context.stop();
        }
      }, _callee, this, [[0, 2]]);
    }));
    function del(_x) {
      return _del.apply(this, arguments);
    }
    return del;
  }();
  _proto2.get = /*#__PURE__*/function () {
    var _get = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2(key) {
      return _regeneratorRuntime.wrap(function (_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            _context2.prev = 0;
            _context2.next = 1;
            return this.driver.get(key);
          case 1:
            return _context2.abrupt("return", _context2.sent);
          case 2:
            _context2.prev = 2;
            _context2["catch"](0);
            return _context2.abrupt("return", null);
          case 3:
          case "end":
            return _context2.stop();
        }
      }, _callee2, this, [[0, 2]]);
    }));
    function get(_x2) {
      return _get.apply(this, arguments);
    }
    return get;
  }();
  _proto2.set = /*#__PURE__*/function () {
    var _set = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee3(key, value) {
      return _regeneratorRuntime.wrap(function (_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            _context3.prev = 0;
            _context3.next = 1;
            return this.driver.set(key, value);
          case 1:
            _context3.next = 3;
            break;
          case 2:
            _context3.prev = 2;
            _context3["catch"](0);
          case 3:
          case "end":
            return _context3.stop();
        }
      }, _callee3, this, [[0, 2]]);
    }));
    function set(_x3, _x4) {
      return _set.apply(this, arguments);
    }
    return set;
  }();
  return _createClass(_SafeStorageDriver);
}();

/**
 * @internal
 */
var _MemoryStorageDriver = /*#__PURE__*/function () {
  function _MemoryStorageDriver() {
    this.backingStore = {};
  }
  var _proto3 = _MemoryStorageDriver.prototype;
  _proto3.get = /*#__PURE__*/function () {
    var _get2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee4(key) {
      var value;
      return _regeneratorRuntime.wrap(function (_context4) {
        while (1) switch (_context4.prev = _context4.next) {
          case 0:
            value = this.backingStore[key];
            if (!(value != null)) {
              _context4.next = 1;
              break;
            }
            return _context4.abrupt("return", value);
          case 1:
            return _context4.abrupt("return", null);
          case 2:
          case "end":
            return _context4.stop();
        }
      }, _callee4, this);
    }));
    function get(_x5) {
      return _get2.apply(this, arguments);
    }
    return get;
  }();
  _proto3.set = /*#__PURE__*/function () {
    var _set2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee5(key, value) {
      return _regeneratorRuntime.wrap(function (_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            this.backingStore[key] = value;
          case 1:
          case "end":
            return _context5.stop();
        }
      }, _callee5, this);
    }));
    function set(_x6, _x7) {
      return _set2.apply(this, arguments);
    }
    return set;
  }();
  _proto3.del = /*#__PURE__*/function () {
    var _del2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee6(key) {
      return _regeneratorRuntime.wrap(function (_context6) {
        while (1) switch (_context6.prev = _context6.next) {
          case 0:
            delete this.backingStore[key];
          case 1:
          case "end":
            return _context6.stop();
        }
      }, _callee6, this);
    }));
    function del(_x8) {
      return _del2.apply(this, arguments);
    }
    return del;
  }();
  return _createClass(_MemoryStorageDriver);
}();

/**
 * TransientTokenStorage stores the refresh token in memory.
 * The refresh token is forgotten as soon as the user quits the app, or
 * the app was killed by the system.
 * When the app launches again next time, no refresh token is found.
 * The user is considered unauthenticated.
 * This implies the user needs to authenticate over again on every app launch.
 *
 * @public
 */
var TransientTokenStorage = /*#__PURE__*/function () {
  function TransientTokenStorage() {
    this.keyMaker = new _KeyMaker();
    this.storageDriver = new _SafeStorageDriver(new _MemoryStorageDriver());
  }
  var _proto4 = TransientTokenStorage.prototype;
  _proto4.setRefreshToken = /*#__PURE__*/function () {
    var _setRefreshToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee7(namespace, refreshToken) {
      return _regeneratorRuntime.wrap(function (_context7) {
        while (1) switch (_context7.prev = _context7.next) {
          case 0:
            _context7.next = 1;
            return this.storageDriver.set(this.keyMaker.keyRefreshToken(namespace), refreshToken);
          case 1:
          case "end":
            return _context7.stop();
        }
      }, _callee7, this);
    }));
    function setRefreshToken(_x9, _x0) {
      return _setRefreshToken.apply(this, arguments);
    }
    return setRefreshToken;
  }();
  _proto4.getRefreshToken = /*#__PURE__*/function () {
    var _getRefreshToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee8(namespace) {
      return _regeneratorRuntime.wrap(function (_context8) {
        while (1) switch (_context8.prev = _context8.next) {
          case 0:
            return _context8.abrupt("return", this.storageDriver.get(this.keyMaker.keyRefreshToken(namespace)));
          case 1:
          case "end":
            return _context8.stop();
        }
      }, _callee8, this);
    }));
    function getRefreshToken(_x1) {
      return _getRefreshToken.apply(this, arguments);
    }
    return getRefreshToken;
  }();
  _proto4.delRefreshToken = /*#__PURE__*/function () {
    var _delRefreshToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee9(namespace) {
      return _regeneratorRuntime.wrap(function (_context9) {
        while (1) switch (_context9.prev = _context9.next) {
          case 0:
            _context9.next = 1;
            return this.storageDriver.del(this.keyMaker.keyRefreshToken(namespace));
          case 1:
          case "end":
            return _context9.stop();
        }
      }, _callee9, this);
    }));
    function delRefreshToken(_x10) {
      return _delRefreshToken.apply(this, arguments);
    }
    return delRefreshToken;
  }();
  return _createClass(TransientTokenStorage);
}();

/**
 * We will get an internal error if we use InterAppSharedStorage directly:
 * InternalError: Internal Error: Unable to analyze the export "InterAppSharedStorage" in
 * /Users/tung/repo/authgear-sdk-js/packages/authgear-core/src/types.d.ts
 * So added this interface to workaround the error
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */

/**
 * @internal
 */
var DefaultDPoPProvider = /*#__PURE__*/function () {
  function DefaultDPoPProvider(_ref) {
    var getNamespace = _ref.getNamespace,
      sharedStorage = _ref.sharedStorage,
      plugin = _ref.plugin;
    this.getNamespace = getNamespace;
    this.sharedStorage = sharedStorage;
    this.plugin = plugin;
  }
  var _proto = DefaultDPoPProvider.prototype;
  _proto.generateDPoPProof = /*#__PURE__*/function () {
    var _generateDPoPProof = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee(_ref2) {
      var htm, htu, existingKeyId, kid, now, payload, jwt, _t, _t2, _t3, _t4, _t5, _t6;
      return _regeneratorRuntime.wrap(function (_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            htm = _ref2.htm, htu = _ref2.htu;
            _context.next = 1;
            return this.plugin.checkDPoPSupported();
          case 1:
            if (_context.sent) {
              _context.next = 2;
              break;
            }
            return _context.abrupt("return", null);
          case 2:
            _context.next = 3;
            return this.sharedStorage.getDPoPKeyID(this.getNamespace());
          case 3:
            existingKeyId = _context.sent;
            if (!(existingKeyId != null)) {
              _context.next = 4;
              break;
            }
            kid = existingKeyId;
            _context.next = 7;
            break;
          case 4:
            _context.next = 5;
            return this.plugin.generateUUID();
          case 5:
            kid = _context.sent;
            _context.next = 6;
            return this.plugin.createDPoPPrivateKey(kid);
          case 6:
            _context.next = 7;
            return this.sharedStorage.setDPoPKeyID(this.getNamespace(), kid);
          case 7:
            now = Math.floor(+new Date() / 1000);
            _t = now;
            _t2 = now + 300;
            _context.next = 8;
            return this.plugin.generateUUID();
          case 8:
            _t3 = _context.sent;
            _t4 = htm;
            _t5 = htu;
            payload = {
              iat: _t,
              exp: _t2,
              jti: _t3,
              htm: _t4,
              htu: _t5
            };
            _context.prev = 9;
            _context.next = 10;
            return this.plugin.signWithDPoPPrivateKey(kid, payload);
          case 10:
            jwt = _context.sent;
            return _context.abrupt("return", jwt);
          case 11:
            _context.prev = 11;
            _t6 = _context["catch"](9);
            _context.next = 12;
            return this.sharedStorage.delDPoPKeyID(this.getNamespace());
          case 12:
            throw _t6;
          case 13:
          case "end":
            return _context.stop();
        }
      }, _callee, this, [[9, 11]]);
    }));
    function generateDPoPProof(_x) {
      return _generateDPoPProof.apply(this, arguments);
    }
    return generateDPoPProof;
  }();
  _proto.computeJKT = /*#__PURE__*/function () {
    var _computeJKT = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2() {
      var existingKeyId, kid, jkt, _t7;
      return _regeneratorRuntime.wrap(function (_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            _context2.next = 1;
            return this.plugin.checkDPoPSupported();
          case 1:
            if (_context2.sent) {
              _context2.next = 2;
              break;
            }
            return _context2.abrupt("return", null);
          case 2:
            _context2.next = 3;
            return this.sharedStorage.getDPoPKeyID(this.getNamespace());
          case 3:
            existingKeyId = _context2.sent;
            kid = existingKeyId;
            if (!(kid == null)) {
              _context2.next = 6;
              break;
            }
            _context2.next = 4;
            return this.plugin.generateUUID();
          case 4:
            kid = _context2.sent;
            _context2.next = 5;
            return this.plugin.createDPoPPrivateKey(kid);
          case 5:
            _context2.next = 6;
            return this.sharedStorage.setDPoPKeyID(this.getNamespace(), kid);
          case 6:
            _context2.prev = 6;
            _context2.next = 7;
            return this.plugin.computeDPoPJKT(kid);
          case 7:
            jkt = _context2.sent;
            return _context2.abrupt("return", jkt);
          case 8:
            _context2.prev = 8;
            _t7 = _context2["catch"](6);
            _context2.next = 9;
            return this.sharedStorage.delDPoPKeyID(this.getNamespace());
          case 9:
            throw _t7;
          case 10:
          case "end":
            return _context2.stop();
        }
      }, _callee2, this, [[6, 8]]);
    }));
    function computeJKT() {
      return _computeJKT.apply(this, arguments);
    }
    return computeJKT;
  }();
  return _createClass(DefaultDPoPProvider);
}();

/**
 * @public
 */
var VERSION = "6.0.0";

var _localStorageStorageDriver = {
  get: function get(key) {
    return _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee() {
      return _regeneratorRuntime.wrap(function (_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            return _context.abrupt("return", window.localStorage.getItem(key));
          case 1:
          case "end":
            return _context.stop();
        }
      }, _callee);
    }))();
  },
  set: function set(key, value) {
    return _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2() {
      return _regeneratorRuntime.wrap(function (_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            window.localStorage.setItem(key, value);
          case 1:
          case "end":
            return _context2.stop();
        }
      }, _callee2);
    }))();
  },
  del: function del(key) {
    return _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee3() {
      return _regeneratorRuntime.wrap(function (_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            window.localStorage.removeItem(key);
          case 1:
          case "end":
            return _context3.stop();
        }
      }, _callee3);
    }))();
  }
};

/**
 * PersistentTokenStorage stores the refresh token in a persistent storage.
 * When the app launches again next time, the refresh token is loaded from the persistent storage.
 * The user is considered authenticated as long as the refresh token is found.
 * However, the validity of the refresh token is not guaranteed.
 * You must call fetchUserInfo to ensure the refresh token is still valid.
 *
 * @public
 */
var PersistentTokenStorage = /*#__PURE__*/function () {
  function PersistentTokenStorage() {
    this.keyMaker = new _KeyMaker();
    this.storageDriver = new _SafeStorageDriver(_localStorageStorageDriver);
  }
  var _proto = PersistentTokenStorage.prototype;
  _proto.setRefreshToken = /*#__PURE__*/function () {
    var _setRefreshToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee4(namespace, refreshToken) {
      return _regeneratorRuntime.wrap(function (_context4) {
        while (1) switch (_context4.prev = _context4.next) {
          case 0:
            _context4.next = 1;
            return this.storageDriver.set(this.keyMaker.keyRefreshToken(namespace), refreshToken);
          case 1:
          case "end":
            return _context4.stop();
        }
      }, _callee4, this);
    }));
    function setRefreshToken(_x, _x2) {
      return _setRefreshToken.apply(this, arguments);
    }
    return setRefreshToken;
  }();
  _proto.getRefreshToken = /*#__PURE__*/function () {
    var _getRefreshToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee5(namespace) {
      return _regeneratorRuntime.wrap(function (_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            return _context5.abrupt("return", this.storageDriver.get(this.keyMaker.keyRefreshToken(namespace)));
          case 1:
          case "end":
            return _context5.stop();
        }
      }, _callee5, this);
    }));
    function getRefreshToken(_x3) {
      return _getRefreshToken.apply(this, arguments);
    }
    return getRefreshToken;
  }();
  _proto.delRefreshToken = /*#__PURE__*/function () {
    var _delRefreshToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee6(namespace) {
      return _regeneratorRuntime.wrap(function (_context6) {
        while (1) switch (_context6.prev = _context6.next) {
          case 0:
            _context6.next = 1;
            return this.storageDriver.del(this.keyMaker.keyRefreshToken(namespace));
          case 1:
          case "end":
            return _context6.stop();
        }
      }, _callee6, this);
    }));
    function delRefreshToken(_x4) {
      return _delRefreshToken.apply(this, arguments);
    }
    return delRefreshToken;
  }();
  return _createClass(PersistentTokenStorage);
}();

/**
 * @internal
 */
var PersistentInterAppSharedStorage = /*#__PURE__*/function () {
  function PersistentInterAppSharedStorage() {
    this.keyMaker = new _KeyMaker();
    this.storageDriver = new _SafeStorageDriver(_localStorageStorageDriver);
  }
  var _proto2 = PersistentInterAppSharedStorage.prototype;
  _proto2.setIDToken = /*#__PURE__*/function () {
    var _setIDToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee7(namespace, idToken) {
      return _regeneratorRuntime.wrap(function (_context7) {
        while (1) switch (_context7.prev = _context7.next) {
          case 0:
            return _context7.abrupt("return", this.storageDriver.set(this.keyMaker.keyIDToken(namespace), idToken));
          case 1:
          case "end":
            return _context7.stop();
        }
      }, _callee7, this);
    }));
    function setIDToken(_x5, _x6) {
      return _setIDToken.apply(this, arguments);
    }
    return setIDToken;
  }();
  _proto2.getIDToken = /*#__PURE__*/function () {
    var _getIDToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee8(namespace) {
      return _regeneratorRuntime.wrap(function (_context8) {
        while (1) switch (_context8.prev = _context8.next) {
          case 0:
            return _context8.abrupt("return", this.storageDriver.get(this.keyMaker.keyIDToken(namespace)));
          case 1:
          case "end":
            return _context8.stop();
        }
      }, _callee8, this);
    }));
    function getIDToken(_x7) {
      return _getIDToken.apply(this, arguments);
    }
    return getIDToken;
  }();
  _proto2.delIDToken = /*#__PURE__*/function () {
    var _delIDToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee9(namespace) {
      return _regeneratorRuntime.wrap(function (_context9) {
        while (1) switch (_context9.prev = _context9.next) {
          case 0:
            return _context9.abrupt("return", this.storageDriver.del(this.keyMaker.keyIDToken(namespace)));
          case 1:
          case "end":
            return _context9.stop();
        }
      }, _callee9, this);
    }));
    function delIDToken(_x8) {
      return _delIDToken.apply(this, arguments);
    }
    return delIDToken;
  }();
  _proto2.setDeviceSecret = /*#__PURE__*/function () {
    var _setDeviceSecret = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee0(namespace, deviceSecret) {
      return _regeneratorRuntime.wrap(function (_context0) {
        while (1) switch (_context0.prev = _context0.next) {
          case 0:
            return _context0.abrupt("return", this.storageDriver.set(this.keyMaker.keyDeviceSecret(namespace), deviceSecret));
          case 1:
          case "end":
            return _context0.stop();
        }
      }, _callee0, this);
    }));
    function setDeviceSecret(_x9, _x0) {
      return _setDeviceSecret.apply(this, arguments);
    }
    return setDeviceSecret;
  }();
  _proto2.getDeviceSecret = /*#__PURE__*/function () {
    var _getDeviceSecret = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee1(namespace) {
      return _regeneratorRuntime.wrap(function (_context1) {
        while (1) switch (_context1.prev = _context1.next) {
          case 0:
            return _context1.abrupt("return", this.storageDriver.get(this.keyMaker.keyDeviceSecret(namespace)));
          case 1:
          case "end":
            return _context1.stop();
        }
      }, _callee1, this);
    }));
    function getDeviceSecret(_x1) {
      return _getDeviceSecret.apply(this, arguments);
    }
    return getDeviceSecret;
  }();
  _proto2.delDeviceSecret = /*#__PURE__*/function () {
    var _delDeviceSecret = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee10(namespace) {
      return _regeneratorRuntime.wrap(function (_context10) {
        while (1) switch (_context10.prev = _context10.next) {
          case 0:
            return _context10.abrupt("return", this.storageDriver.del(this.keyMaker.keyDeviceSecret(namespace)));
          case 1:
          case "end":
            return _context10.stop();
        }
      }, _callee10, this);
    }));
    function delDeviceSecret(_x10) {
      return _delDeviceSecret.apply(this, arguments);
    }
    return delDeviceSecret;
  }();
  _proto2.setDPoPKeyID = /*#__PURE__*/function () {
    var _setDPoPKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee11(namespace, kid) {
      return _regeneratorRuntime.wrap(function (_context11) {
        while (1) switch (_context11.prev = _context11.next) {
          case 0:
            return _context11.abrupt("return", this.storageDriver.set(this.keyMaker.keyDPoPKeyID(namespace), kid));
          case 1:
          case "end":
            return _context11.stop();
        }
      }, _callee11, this);
    }));
    function setDPoPKeyID(_x11, _x12) {
      return _setDPoPKeyID.apply(this, arguments);
    }
    return setDPoPKeyID;
  }();
  _proto2.getDPoPKeyID = /*#__PURE__*/function () {
    var _getDPoPKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee12(namespace) {
      return _regeneratorRuntime.wrap(function (_context12) {
        while (1) switch (_context12.prev = _context12.next) {
          case 0:
            return _context12.abrupt("return", this.storageDriver.get(this.keyMaker.keyDPoPKeyID(namespace)));
          case 1:
          case "end":
            return _context12.stop();
        }
      }, _callee12, this);
    }));
    function getDPoPKeyID(_x13) {
      return _getDPoPKeyID.apply(this, arguments);
    }
    return getDPoPKeyID;
  }();
  _proto2.delDPoPKeyID = /*#__PURE__*/function () {
    var _delDPoPKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee13(namespace) {
      return _regeneratorRuntime.wrap(function (_context13) {
        while (1) switch (_context13.prev = _context13.next) {
          case 0:
            return _context13.abrupt("return", this.storageDriver.del(this.keyMaker.keyDPoPKeyID(namespace)));
          case 1:
          case "end":
            return _context13.stop();
        }
      }, _callee13, this);
    }));
    function delDPoPKeyID(_x14) {
      return _delDPoPKeyID.apply(this, arguments);
    }
    return delDPoPKeyID;
  }();
  _proto2.onLogout = /*#__PURE__*/function () {
    var _onLogout = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee14(namespace) {
      return _regeneratorRuntime.wrap(function (_context14) {
        while (1) switch (_context14.prev = _context14.next) {
          case 0:
            _context14.next = 1;
            return this.delDPoPKeyID(namespace);
          case 1:
            _context14.next = 2;
            return this.delDeviceSecret(namespace);
          case 2:
            _context14.next = 3;
            return this.delIDToken(namespace);
          case 3:
          case "end":
            return _context14.stop();
        }
      }, _callee14, this);
    }));
    function onLogout(_x15) {
      return _onLogout.apply(this, arguments);
    }
    return onLogout;
  }();
  return _createClass(PersistentInterAppSharedStorage);
}();

/**
 * @internal
 */
var PersistentContainerStorage = /*#__PURE__*/function () {
  function PersistentContainerStorage() {
    this.keyMaker = new _KeyMaker();
    this.storageDriver = new _SafeStorageDriver(_localStorageStorageDriver);
  }
  var _proto3 = PersistentContainerStorage.prototype;
  _proto3.setOIDCCodeVerifier = /*#__PURE__*/function () {
    var _setOIDCCodeVerifier = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee15(namespace, code) {
      return _regeneratorRuntime.wrap(function (_context15) {
        while (1) switch (_context15.prev = _context15.next) {
          case 0:
            _context15.next = 1;
            return this.storageDriver.set(this.keyMaker.keyOIDCCodeVerifier(namespace), code);
          case 1:
          case "end":
            return _context15.stop();
        }
      }, _callee15, this);
    }));
    function setOIDCCodeVerifier(_x16, _x17) {
      return _setOIDCCodeVerifier.apply(this, arguments);
    }
    return setOIDCCodeVerifier;
  }();
  _proto3.setAnonymousKeyID = /*#__PURE__*/function () {
    var _setAnonymousKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee16(namespace, kid) {
      return _regeneratorRuntime.wrap(function (_context16) {
        while (1) switch (_context16.prev = _context16.next) {
          case 0:
            _context16.next = 1;
            return this.storageDriver.set(this.keyMaker.keyAnonymousKeyID(namespace), kid);
          case 1:
          case "end":
            return _context16.stop();
        }
      }, _callee16, this);
    }));
    function setAnonymousKeyID(_x18, _x19) {
      return _setAnonymousKeyID.apply(this, arguments);
    }
    return setAnonymousKeyID;
  }();
  _proto3.setBiometricKeyID = /*#__PURE__*/function () {
    var _setBiometricKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee17(namespace, kid) {
      return _regeneratorRuntime.wrap(function (_context17) {
        while (1) switch (_context17.prev = _context17.next) {
          case 0:
            _context17.next = 1;
            return this.storageDriver.set(this.keyMaker.keyBiometricKeyID(namespace), kid);
          case 1:
          case "end":
            return _context17.stop();
        }
      }, _callee17, this);
    }));
    function setBiometricKeyID(_x20, _x21) {
      return _setBiometricKeyID.apply(this, arguments);
    }
    return setBiometricKeyID;
  }();
  _proto3.getOIDCCodeVerifier = /*#__PURE__*/function () {
    var _getOIDCCodeVerifier = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee18(namespace) {
      return _regeneratorRuntime.wrap(function (_context18) {
        while (1) switch (_context18.prev = _context18.next) {
          case 0:
            return _context18.abrupt("return", this.storageDriver.get(this.keyMaker.keyOIDCCodeVerifier(namespace)));
          case 1:
          case "end":
            return _context18.stop();
        }
      }, _callee18, this);
    }));
    function getOIDCCodeVerifier(_x22) {
      return _getOIDCCodeVerifier.apply(this, arguments);
    }
    return getOIDCCodeVerifier;
  }();
  _proto3.getAnonymousKeyID = /*#__PURE__*/function () {
    var _getAnonymousKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee19(namespace) {
      return _regeneratorRuntime.wrap(function (_context19) {
        while (1) switch (_context19.prev = _context19.next) {
          case 0:
            return _context19.abrupt("return", this.storageDriver.get(this.keyMaker.keyAnonymousKeyID(namespace)));
          case 1:
          case "end":
            return _context19.stop();
        }
      }, _callee19, this);
    }));
    function getAnonymousKeyID(_x23) {
      return _getAnonymousKeyID.apply(this, arguments);
    }
    return getAnonymousKeyID;
  }();
  _proto3.getBiometricKeyID = /*#__PURE__*/function () {
    var _getBiometricKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee20(namespace) {
      return _regeneratorRuntime.wrap(function (_context20) {
        while (1) switch (_context20.prev = _context20.next) {
          case 0:
            return _context20.abrupt("return", this.storageDriver.get(this.keyMaker.keyBiometricKeyID(namespace)));
          case 1:
          case "end":
            return _context20.stop();
        }
      }, _callee20, this);
    }));
    function getBiometricKeyID(_x24) {
      return _getBiometricKeyID.apply(this, arguments);
    }
    return getBiometricKeyID;
  }();
  _proto3.delOIDCCodeVerifier = /*#__PURE__*/function () {
    var _delOIDCCodeVerifier = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee21(namespace) {
      return _regeneratorRuntime.wrap(function (_context21) {
        while (1) switch (_context21.prev = _context21.next) {
          case 0:
            _context21.next = 1;
            return this.storageDriver.del(this.keyMaker.keyOIDCCodeVerifier(namespace));
          case 1:
          case "end":
            return _context21.stop();
        }
      }, _callee21, this);
    }));
    function delOIDCCodeVerifier(_x25) {
      return _delOIDCCodeVerifier.apply(this, arguments);
    }
    return delOIDCCodeVerifier;
  }();
  _proto3.delAnonymousKeyID = /*#__PURE__*/function () {
    var _delAnonymousKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee22(namespace) {
      return _regeneratorRuntime.wrap(function (_context22) {
        while (1) switch (_context22.prev = _context22.next) {
          case 0:
            _context22.next = 1;
            return this.storageDriver.del(this.keyMaker.keyAnonymousKeyID(namespace));
          case 1:
          case "end":
            return _context22.stop();
        }
      }, _callee22, this);
    }));
    function delAnonymousKeyID(_x26) {
      return _delAnonymousKeyID.apply(this, arguments);
    }
    return delAnonymousKeyID;
  }();
  _proto3.delBiometricKeyID = /*#__PURE__*/function () {
    var _delBiometricKeyID = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee23(namespace) {
      return _regeneratorRuntime.wrap(function (_context23) {
        while (1) switch (_context23.prev = _context23.next) {
          case 0:
            _context23.next = 1;
            return this.storageDriver.del(this.keyMaker.keyBiometricKeyID(namespace));
          case 1:
          case "end":
            return _context23.stop();
        }
      }, _callee23, this);
    }));
    function delBiometricKeyID(_x27) {
      return _delBiometricKeyID.apply(this, arguments);
    }
    return delBiometricKeyID;
  }();
  return _createClass(PersistentContainerStorage);
}();

// windowCryptoSubtleDigest is window.crypto.subtle.digest with IE 11 support.
function windowCryptoSubtleDigest(_x, _x2) {
  return _windowCryptoSubtleDigest.apply(this, arguments);
}
function _windowCryptoSubtleDigest() {
  _windowCryptoSubtleDigest = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee(algorithm, data) {
    var promiseOrEvent;
    return _regeneratorRuntime.wrap(function (_context) {
      while (1) switch (_context.prev = _context.next) {
        case 0:
          promiseOrEvent = window.crypto.subtle.digest(algorithm, data.buffer); // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
          if (!promiseOrEvent.then) {
            _context.next = 1;
            break;
          }
          return _context.abrupt("return", promiseOrEvent.then(function (output) {
            return new Uint8Array(output);
          }));
        case 1:
          return _context.abrupt("return", new Promise(function (resolve, reject) {
            promiseOrEvent.oncomplete = function (output) {
              resolve(new Uint8Array(output));
            };
            promiseOrEvent.onerror = function (err) {
              reject(err);
            };
          }));
        case 2:
        case "end":
          return _context.stop();
      }
    }, _callee);
  }));
  return _windowCryptoSubtleDigest.apply(this, arguments);
}
function sha256(_x3) {
  return _sha.apply(this, arguments);
}
function _sha() {
  _sha = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2(s) {
    var bytes;
    return _regeneratorRuntime.wrap(function (_context2) {
      while (1) switch (_context2.prev = _context2.next) {
        case 0:
          bytes = _encodeUTF8(s);
          return _context2.abrupt("return", windowCryptoSubtleDigest("SHA-256", bytes));
        case 1:
        case "end":
          return _context2.stop();
      }
    }, _callee2);
  }));
  return _sha.apply(this, arguments);
}
function computeCodeChallenge(_x4) {
  return _computeCodeChallenge.apply(this, arguments);
}
function _computeCodeChallenge() {
  _computeCodeChallenge = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee3(codeVerifier) {
    var hash, base64;
    return _regeneratorRuntime.wrap(function (_context3) {
      while (1) switch (_context3.prev = _context3.next) {
        case 0:
          _context3.next = 1;
          return sha256(codeVerifier);
        case 1:
          hash = _context3.sent;
          base64 = _base64URLEncode(hash);
          return _context3.abrupt("return", base64);
        case 2:
        case "end":
          return _context3.stop();
      }
    }, _callee3);
  }));
  return _computeCodeChallenge.apply(this, arguments);
}
function generateCodeVerifier() {
  var arr = new Uint8Array(32);
  window.crypto.getRandomValues(arr);
  var base64 = _base64URLEncode(arr);
  return base64;
}

/**
 * @public
 */

/**
 * WebContainer is the entrypoint of the SDK.
 * An instance of a container allows the user to authenticate, reauthenticate, etc.
 *
 * Every container has a name.
 * The default name of a container is `default`.
 * If your app supports multi login sessions, you can use multiple containers with different names.
 * You are responsible for managing the list of names in this case.
 *
 * @public
 */
var WebContainer = /*#__PURE__*/function () {
  function WebContainer(options) {
    var o = _extends({}, options);
    var apiClient = new _BaseAPIClient({
      // fetch() expects the context of the function to be window.
      // If that does not hold, we will get the following error:
      //
      //   TypeError: Failed to execute 'fetch' on 'Window': Illegal invocation
      //
      // To prevent this, we bind window to fetch().
      fetch: window.fetch.bind(window),
      Request: Request,
      dpopProvider: null
    });
    this.baseContainer = new _BaseContainer(o, apiClient, this);
    this.baseContainer.apiClient._delegate = this;
    this.storage = new PersistentContainerStorage();
    this.tokenStorage = new PersistentTokenStorage();
    this.sharedStorage = new PersistentInterAppSharedStorage();
    this.sessionType = "refresh_token";
  }

  /**
   * implements _APIClientDelegate
   *
   * @internal
   */
  var _proto = WebContainer.prototype;
  _proto.getAccessToken = function getAccessToken() {
    return this.baseContainer.accessToken;
  }

  /**
   * implements _APIClientDelegate
   *
   * @internal
   */;
  _proto.shouldRefreshAccessToken = function shouldRefreshAccessToken() {
    return this.baseContainer.shouldRefreshAccessToken();
  }

  /**
   * implements _BaseContainerDelegate
   *
   * @internal
   */;
  _proto.onSessionStateChange = function onSessionStateChange(reason) {
    var _this$delegate;
    (_this$delegate = this.delegate) == null || _this$delegate.onSessionStateChange(this, reason);
  }

  /**
   * getIDTokenHint() returns the ID token for the OIDC id_token_hint parameter.
   *
   * @public
   */;
  _proto.getIDTokenHint = function getIDTokenHint() {
    return this.baseContainer.getIDTokenHint();
  }

  /**
   * canReauthenticate() reports whether the current user can reauthenticate.
   * The information comes from the ID token and the ID token is NOT verified.
   *
   * @public
   */;
  _proto.canReauthenticate = function canReauthenticate() {
    return this.baseContainer.canReauthenticate();
  }

  /**
   * getAuthTime() reports the last time the user was authenticated.
   * The information comes from the ID token and the ID token is NOT verified.
   *
   * @public
   */;
  _proto.getAuthTime = function getAuthTime() {
    return this.baseContainer.getAuthTime();
  }

  /**
   * refreshIDToken() asks the server to issue an ID token with latest claims.
   * After refreshing, getIDTokenHint() and canReauthenticate() may return up-to-date value.
   *
   * @public
   */;
  _proto.refreshIDToken =
  /*#__PURE__*/
  function () {
    var _refreshIDToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee() {
      return _regeneratorRuntime.wrap(function (_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            _context.next = 1;
            return this.refreshAccessTokenIfNeeded();
          case 1:
            return _context.abrupt("return", this.baseContainer.refreshIDToken());
          case 2:
          case "end":
            return _context.stop();
        }
      }, _callee, this);
    }));
    function refreshIDToken() {
      return _refreshIDToken.apply(this, arguments);
    }
    return refreshIDToken;
  }()
  /**
   * configure() configures the container with the client ID and the endpoint.
   * It also does local IO to retrieve the refresh token.
   * It only obtains the refresh token locally and no network call will
   * be triggered. So the session state maybe outdated for some reason, e.g.
   * user session is revoked. fetchUserInfo should be called to obtain the
   * latest user session state.
   *
   * configure() can be called more than once if it failed.
   * Otherwise, it is NOT recommended to call it more than once.
   *
   * @public
   */
;
  _proto.configure =
  /*#__PURE__*/
  function () {
    var _configure = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee2(options) {
      var _options$fetch;
      var _options$isSSOEnabled, refreshToken, _t;
      return _regeneratorRuntime.wrap(function (_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            this.clientID = options.clientID;
            this.baseContainer.apiClient._fetch = (_options$fetch = options.fetch) != null ? _options$fetch : window.fetch.bind(window);
            this.baseContainer.apiClient.endpoint = options.endpoint;
            if (options.sessionType != null) {
              this.sessionType = options.sessionType;
            }

            // When sessionType is "cookie", isSSOEnabled must be true so that IDP session will be generated.
            if (this.sessionType === "cookie") {
              this.isSSOEnabled = true;
            } else {
              this.isSSOEnabled = (_options$isSSOEnabled = options.isSSOEnabled) != null ? _options$isSSOEnabled : false;
            }
            _t = this.sessionType;
            _context2.next = _t === "cookie" ? 1 : _t === "refresh_token" ? 2 : 4;
            break;
          case 1:
            this.baseContainer._updateSessionState(SessionState.Unknown, SessionStateChangeReason.NoToken);
            return _context2.abrupt("continue", 4);
          case 2:
            _context2.next = 3;
            return this.tokenStorage.getRefreshToken(this.name);
          case 3:
            refreshToken = _context2.sent;
            this.baseContainer.refreshToken = refreshToken != null ? refreshToken : undefined;
            this.baseContainer.accessToken = undefined;
            if (this.baseContainer.refreshToken != null) {
              // consider user as logged in if refresh token is available
              this.baseContainer._updateSessionState(SessionState.Authenticated, SessionStateChangeReason.FoundToken);
            } else {
              this.baseContainer._updateSessionState(SessionState.NoSession, SessionStateChangeReason.NoToken);
            }
            return _context2.abrupt("continue", 4);
          case 4:
          case "end":
            return _context2.stop();
        }
      }, _callee2, this);
    }));
    function configure(_x) {
      return _configure.apply(this, arguments);
    }
    return configure;
  }()
  /**
   * @internal
   */
  // eslint-disable-next-line class-methods-use-this
;
  _proto._setupCodeVerifier =
  /*#__PURE__*/
  function () {
    var _setupCodeVerifier2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee3() {
      var codeVerifier, codeChallenge;
      return _regeneratorRuntime.wrap(function (_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            codeVerifier = generateCodeVerifier();
            _context3.next = 1;
            return computeCodeChallenge(codeVerifier);
          case 1:
            codeChallenge = _context3.sent;
            return _context3.abrupt("return", {
              verifier: codeVerifier,
              challenge: codeChallenge
            });
          case 2:
          case "end":
            return _context3.stop();
        }
      }, _callee3);
    }));
    function _setupCodeVerifier() {
      return _setupCodeVerifier2.apply(this, arguments);
    }
    return _setupCodeVerifier;
  }()
  /**
   * Start authentication by redirecting to the authorization endpoint.
   */
;
  _proto.startAuthentication =
  /*#__PURE__*/
  function () {
    var _startAuthentication = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee4(options) {
      var authorizeEndpoint;
      return _regeneratorRuntime.wrap(function (_context4) {
        while (1) switch (_context4.prev = _context4.next) {
          case 0:
            _context4.next = 1;
            return this.authorizeEndpoint(options);
          case 1:
            authorizeEndpoint = _context4.sent;
            window.location.href = authorizeEndpoint;
          case 2:
          case "end":
            return _context4.stop();
        }
      }, _callee4, this);
    }));
    function startAuthentication(_x2) {
      return _startAuthentication.apply(this, arguments);
    }
    return startAuthentication;
  }()
  /**
   * Start settings action by redirecting to the authorization endpoint.
   *
   * @internal
   */
;
  _proto.startSettingsAction =
  /*#__PURE__*/
  function () {
    var _startSettingsAction = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee5(action, options) {
      var idToken, refreshToken, appSessionToken, loginHint, endpoint;
      return _regeneratorRuntime.wrap(function (_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            idToken = this.getIDTokenHint();
            if (!(idToken == null || !this.canReauthenticate())) {
              _context5.next = 1;
              break;
            }
            throw new Error("You can only trigger settings action when authenticated");
          case 1:
            if (!(this.sessionType === "refresh_token")) {
              _context5.next = 6;
              break;
            }
            _context5.next = 2;
            return this.tokenStorage.getRefreshToken(this.name);
          case 2:
            refreshToken = _context5.sent;
            if (refreshToken) {
              _context5.next = 3;
              break;
            }
            throw new AuthgearError("refresh token not found");
          case 3:
            _context5.next = 4;
            return this.baseContainer._getAppSessionToken(refreshToken);
          case 4:
            appSessionToken = _context5.sent;
            loginHint = "https://authgear.com/login_hint?type=app_session_token&app_session_token=".concat(encodeURIComponent(appSessionToken));
            _context5.next = 5;
            return this.baseContainer.authorizeEndpoint(_extends({}, options, {
              loginHint: loginHint,
              idTokenHint: idToken,
              responseType: "urn:authgear:params:oauth:response-type:settings-action",
              scope: this.baseContainer.getSettingsActionScopes(),
              xSettingsAction: action,
              xSettingsActionQuery: {
                q_login_id: options.qLoginID
              }
            }));
          case 5:
            endpoint = _context5.sent;
            window.location.href = endpoint;
          case 6:
          case "end":
            return _context5.stop();
        }
      }, _callee5, this);
    }));
    function startSettingsAction(_x3, _x4) {
      return _startSettingsAction.apply(this, arguments);
    }
    return startSettingsAction;
  }()
  /**
   * Start settings action "change_password" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startChangePassword =
  /*#__PURE__*/
  function () {
    var _startChangePassword = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee6(options) {
      return _regeneratorRuntime.wrap(function (_context6) {
        while (1) switch (_context6.prev = _context6.next) {
          case 0:
            _context6.next = 1;
            return this.startSettingsAction(SettingsAction.ChangePassword, options);
          case 1:
          case "end":
            return _context6.stop();
        }
      }, _callee6, this);
    }));
    function startChangePassword(_x5) {
      return _startChangePassword.apply(this, arguments);
    }
    return startChangePassword;
  }()
  /**
   * Start settings action "delete_account" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startDeleteAccount =
  /*#__PURE__*/
  function () {
    var _startDeleteAccount = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee7(options) {
      return _regeneratorRuntime.wrap(function (_context7) {
        while (1) switch (_context7.prev = _context7.next) {
          case 0:
            _context7.next = 1;
            return this.startSettingsAction(SettingsAction.DeleteAccount, options);
          case 1:
          case "end":
            return _context7.stop();
        }
      }, _callee7, this);
    }));
    function startDeleteAccount(_x6) {
      return _startDeleteAccount.apply(this, arguments);
    }
    return startDeleteAccount;
  }()
  /**
   * Start settings action "add_email" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startAddEmail =
  /*#__PURE__*/
  function () {
    var _startAddEmail = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee8(options) {
      return _regeneratorRuntime.wrap(function (_context8) {
        while (1) switch (_context8.prev = _context8.next) {
          case 0:
            _context8.next = 1;
            return this.startSettingsAction(SettingsAction.AddEmail, options);
          case 1:
          case "end":
            return _context8.stop();
        }
      }, _callee8, this);
    }));
    function startAddEmail(_x7) {
      return _startAddEmail.apply(this, arguments);
    }
    return startAddEmail;
  }()
  /**
   * Start settings action "add_phone" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startAddPhone =
  /*#__PURE__*/
  function () {
    var _startAddPhone = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee9(options) {
      return _regeneratorRuntime.wrap(function (_context9) {
        while (1) switch (_context9.prev = _context9.next) {
          case 0:
            _context9.next = 1;
            return this.startSettingsAction(SettingsAction.AddPhone, options);
          case 1:
          case "end":
            return _context9.stop();
        }
      }, _callee9, this);
    }));
    function startAddPhone(_x8) {
      return _startAddPhone.apply(this, arguments);
    }
    return startAddPhone;
  }()
  /**
   * Start settings action "add_username" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startAddUsername =
  /*#__PURE__*/
  function () {
    var _startAddUsername = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee0(options) {
      return _regeneratorRuntime.wrap(function (_context0) {
        while (1) switch (_context0.prev = _context0.next) {
          case 0:
            _context0.next = 1;
            return this.startSettingsAction(SettingsAction.AddUsername, options);
          case 1:
          case "end":
            return _context0.stop();
        }
      }, _callee0, this);
    }));
    function startAddUsername(_x9) {
      return _startAddUsername.apply(this, arguments);
    }
    return startAddUsername;
  }()
  /**
   * Start settings action "change_email" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startChangeEmail =
  /*#__PURE__*/
  function () {
    var _startChangeEmail = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee1(originalEmail, options) {
      return _regeneratorRuntime.wrap(function (_context1) {
        while (1) switch (_context1.prev = _context1.next) {
          case 0:
            _context1.next = 1;
            return this.startSettingsAction(SettingsAction.ChangeEmail, _extends({}, options, {
              qLoginID: originalEmail
            }));
          case 1:
          case "end":
            return _context1.stop();
        }
      }, _callee1, this);
    }));
    function startChangeEmail(_x0, _x1) {
      return _startChangeEmail.apply(this, arguments);
    }
    return startChangeEmail;
  }()
  /**
   * Start settings action "change_phone" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startChangePhone =
  /*#__PURE__*/
  function () {
    var _startChangePhone = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee10(originalPhone, options) {
      return _regeneratorRuntime.wrap(function (_context10) {
        while (1) switch (_context10.prev = _context10.next) {
          case 0:
            _context10.next = 1;
            return this.startSettingsAction(SettingsAction.ChangePhone, _extends({}, options, {
              qLoginID: originalPhone
            }));
          case 1:
          case "end":
            return _context10.stop();
        }
      }, _callee10, this);
    }));
    function startChangePhone(_x10, _x11) {
      return _startChangePhone.apply(this, arguments);
    }
    return startChangePhone;
  }()
  /**
   * Start settings action "change_username" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startChangeUsername =
  /*#__PURE__*/
  function () {
    var _startChangeUsername = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee11(originalUsername, options) {
      return _regeneratorRuntime.wrap(function (_context11) {
        while (1) switch (_context11.prev = _context11.next) {
          case 0:
            _context11.next = 1;
            return this.startSettingsAction(SettingsAction.ChangeUsername, _extends({}, options, {
              qLoginID: originalUsername
            }));
          case 1:
          case "end":
            return _context11.stop();
        }
      }, _callee11, this);
    }));
    function startChangeUsername(_x12, _x13) {
      return _startChangeUsername.apply(this, arguments);
    }
    return startChangeUsername;
  }()
  /**
   * Start reauthentication by redirecting to the authorization endpoint.
   */
;
  _proto.startReauthentication =
  /*#__PURE__*/
  function () {
    var _startReauthentication = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee12(options) {
      var _options$maxAge;
      var idToken, maxAge, endpoint;
      return _regeneratorRuntime.wrap(function (_context12) {
        while (1) switch (_context12.prev = _context12.next) {
          case 0:
            idToken = this.getIDTokenHint();
            if (!(idToken == null || !this.canReauthenticate())) {
              _context12.next = 1;
              break;
            }
            throw new Error("You can only trigger reauthentication when canReauthenticate() returns true");
          case 1:
            maxAge = (_options$maxAge = options.maxAge) != null ? _options$maxAge : 0;
            _context12.next = 2;
            return this.baseContainer.authorizeEndpoint(_extends({}, options, {
              maxAge: maxAge,
              idTokenHint: idToken,
              responseType: "code",
              scope: this.baseContainer.getReauthenticateScopes()
            }));
          case 2:
            endpoint = _context12.sent;
            window.location.href = endpoint;
          case 3:
          case "end":
            return _context12.stop();
        }
      }, _callee12, this);
    }));
    function startReauthentication(_x14) {
      return _startReauthentication.apply(this, arguments);
    }
    return startReauthentication;
  }()
  /**
   * Start promote anonymous user by redirecting to the authorization endpoint.
   */
;
  _proto.startPromoteAnonymousUser =
  /*#__PURE__*/
  function () {
    var _startPromoteAnonymousUser = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee13(options) {
      var _yield$this$tokenStor;
      var refreshToken, _yield$this$baseConta, promotion_code, loginHint, authorizeEndpoint, _t2, _t3, _t4;
      return _regeneratorRuntime.wrap(function (_context13) {
        while (1) switch (_context13.prev = _context13.next) {
          case 0:
            if (!(this.sessionType === "cookie")) {
              _context13.next = 1;
              break;
            }
            _t2 = undefined;
            _context13.next = 5;
            break;
          case 1:
            _context13.next = 2;
            return this.tokenStorage.getRefreshToken(this.name);
          case 2:
            _t3 = _yield$this$tokenStor = _context13.sent;
            if (!(_t3 != null)) {
              _context13.next = 3;
              break;
            }
            _t4 = _yield$this$tokenStor;
            _context13.next = 4;
            break;
          case 3:
            _t4 = undefined;
          case 4:
            _t2 = _t4;
          case 5:
            refreshToken = _t2;
            _context13.next = 6;
            return this.baseContainer.apiClient.anonymousUserPromotionCode(this.sessionType, refreshToken);
          case 6:
            _yield$this$baseConta = _context13.sent;
            promotion_code = _yield$this$baseConta.promotion_code;
            loginHint = "https://authgear.com/login_hint?type=anonymous&promotion_code=".concat(encodeURIComponent(promotion_code));
            _context13.next = 7;
            return this.authorizeEndpoint(_extends({}, options, {
              prompt: PromptOption.Login,
              loginHint: loginHint
            }));
          case 7:
            authorizeEndpoint = _context13.sent;
            window.location.href = authorizeEndpoint;
          case 8:
          case "end":
            return _context13.stop();
        }
      }, _callee13, this);
    }));
    function startPromoteAnonymousUser(_x15) {
      return _startPromoteAnonymousUser.apply(this, arguments);
    }
    return startPromoteAnonymousUser;
  }()
  /**
   * Finish authentication.
   *
   * It may reject with OAuthError.
   */
;
  _proto.finishAuthentication =
  /*#__PURE__*/
  function () {
    var _finishAuthentication = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee14() {
      var codeRequired;
      return _regeneratorRuntime.wrap(function (_context14) {
        while (1) switch (_context14.prev = _context14.next) {
          case 0:
            // only sessionType === "cookie" doesn't require authorization code
            codeRequired = this.sessionType === "cookie" ? false : true;
            return _context14.abrupt("return", this.baseContainer._finishAuthentication(window.location.href, codeRequired));
          case 1:
          case "end":
            return _context14.stop();
        }
      }, _callee14, this);
    }));
    function finishAuthentication() {
      return _finishAuthentication.apply(this, arguments);
    }
    return finishAuthentication;
  }()
  /**
   * Finish reauthentication.
   *
   * It may reject with OAuthError.
   */
;
  _proto.finishReauthentication =
  /*#__PURE__*/
  function () {
    var _finishReauthentication = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee15() {
      return _regeneratorRuntime.wrap(function (_context15) {
        while (1) switch (_context15.prev = _context15.next) {
          case 0:
            return _context15.abrupt("return", this.baseContainer._finishReauthentication(window.location.href));
          case 1:
          case "end":
            return _context15.stop();
        }
      }, _callee15, this);
    }));
    function finishReauthentication() {
      return _finishReauthentication.apply(this, arguments);
    }
    return finishReauthentication;
  }()
  /**
   * Finish settings action.
   *
   * It may reject with OAuthError.
   *
   * @internal
   */
;
  _proto.finishSettingsAction =
  /*#__PURE__*/
  function () {
    var _finishSettingsAction = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee16() {
      return _regeneratorRuntime.wrap(function (_context16) {
        while (1) switch (_context16.prev = _context16.next) {
          case 0:
            return _context16.abrupt("return", this.baseContainer._finishSettingsAction(window.location.href));
          case 1:
          case "end":
            return _context16.stop();
        }
      }, _callee16, this);
    }));
    function finishSettingsAction() {
      return _finishSettingsAction.apply(this, arguments);
    }
    return finishSettingsAction;
  }()
  /**
   * Finish settings action "change_password".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishChangePassword =
  /*#__PURE__*/
  function () {
    var _finishChangePassword = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee17() {
      return _regeneratorRuntime.wrap(function (_context17) {
        while (1) switch (_context17.prev = _context17.next) {
          case 0:
            return _context17.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context17.stop();
        }
      }, _callee17, this);
    }));
    function finishChangePassword() {
      return _finishChangePassword.apply(this, arguments);
    }
    return finishChangePassword;
  }()
  /**
   * Finish settings action "delete_account".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishDeleteAccount =
  /*#__PURE__*/
  function () {
    var _finishDeleteAccount = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee18() {
      return _regeneratorRuntime.wrap(function (_context18) {
        while (1) switch (_context18.prev = _context18.next) {
          case 0:
            _context18.next = 1;
            return this.finishSettingsAction();
          case 1:
            return _context18.abrupt("return", this.baseContainer._clearSession(SessionStateChangeReason.Invalid));
          case 2:
          case "end":
            return _context18.stop();
        }
      }, _callee18, this);
    }));
    function finishDeleteAccount() {
      return _finishDeleteAccount.apply(this, arguments);
    }
    return finishDeleteAccount;
  }()
  /**
   * Finish settings action "add_email".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishAddEmail =
  /*#__PURE__*/
  function () {
    var _finishAddEmail = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee19() {
      return _regeneratorRuntime.wrap(function (_context19) {
        while (1) switch (_context19.prev = _context19.next) {
          case 0:
            return _context19.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context19.stop();
        }
      }, _callee19, this);
    }));
    function finishAddEmail() {
      return _finishAddEmail.apply(this, arguments);
    }
    return finishAddEmail;
  }()
  /**
   * Finish settings action "add_phone".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishAddPhone =
  /*#__PURE__*/
  function () {
    var _finishAddPhone = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee20() {
      return _regeneratorRuntime.wrap(function (_context20) {
        while (1) switch (_context20.prev = _context20.next) {
          case 0:
            return _context20.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context20.stop();
        }
      }, _callee20, this);
    }));
    function finishAddPhone() {
      return _finishAddPhone.apply(this, arguments);
    }
    return finishAddPhone;
  }()
  /**
   * Finish settings action "add_username".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishAddUsername =
  /*#__PURE__*/
  function () {
    var _finishAddUsername = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee21() {
      return _regeneratorRuntime.wrap(function (_context21) {
        while (1) switch (_context21.prev = _context21.next) {
          case 0:
            return _context21.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context21.stop();
        }
      }, _callee21, this);
    }));
    function finishAddUsername() {
      return _finishAddUsername.apply(this, arguments);
    }
    return finishAddUsername;
  }()
  /**
   * Finish settings action "change_email".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishChangeEmail =
  /*#__PURE__*/
  function () {
    var _finishChangeEmail = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee22() {
      return _regeneratorRuntime.wrap(function (_context22) {
        while (1) switch (_context22.prev = _context22.next) {
          case 0:
            return _context22.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context22.stop();
        }
      }, _callee22, this);
    }));
    function finishChangeEmail() {
      return _finishChangeEmail.apply(this, arguments);
    }
    return finishChangeEmail;
  }()
  /**
   * Finish settings action "change_phone".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishChangePhone =
  /*#__PURE__*/
  function () {
    var _finishChangePhone = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee23() {
      return _regeneratorRuntime.wrap(function (_context23) {
        while (1) switch (_context23.prev = _context23.next) {
          case 0:
            return _context23.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context23.stop();
        }
      }, _callee23, this);
    }));
    function finishChangePhone() {
      return _finishChangePhone.apply(this, arguments);
    }
    return finishChangePhone;
  }()
  /**
   * Finish settings action "change_username".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishChangeUsername =
  /*#__PURE__*/
  function () {
    var _finishChangeUsername = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee24() {
      return _regeneratorRuntime.wrap(function (_context24) {
        while (1) switch (_context24.prev = _context24.next) {
          case 0:
            return _context24.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context24.stop();
        }
      }, _callee24, this);
    }));
    function finishChangeUsername() {
      return _finishChangeUsername.apply(this, arguments);
    }
    return finishChangeUsername;
  }()
  /**
   * Start settings action "link_oauth" by redirecting to the authorization endpoint.
   *
   * @public
   */
;
  _proto.startLinkOAuth =
  /*#__PURE__*/
  function () {
    var _startLinkOAuth = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee25(options) {
      return _regeneratorRuntime.wrap(function (_context25) {
        while (1) switch (_context25.prev = _context25.next) {
          case 0:
            _context25.next = 1;
            return this.startSettingsAction(SettingsAction.LinkOAuth, options);
          case 1:
          case "end":
            return _context25.stop();
        }
      }, _callee25, this);
    }));
    function startLinkOAuth(_x16) {
      return _startLinkOAuth.apply(this, arguments);
    }
    return startLinkOAuth;
  }()
  /**
   * Finish settings action "link_oauth".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishLinkOAuth =
  /*#__PURE__*/
  function () {
    var _finishLinkOAuth = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee26() {
      return _regeneratorRuntime.wrap(function (_context26) {
        while (1) switch (_context26.prev = _context26.next) {
          case 0:
            return _context26.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context26.stop();
        }
      }, _callee26, this);
    }));
    function finishLinkOAuth() {
      return _finishLinkOAuth.apply(this, arguments);
    }
    return finishLinkOAuth;
  }()
  /**
   * Start settings action "unlink_oauth" by redirecting to the settings page.
   *
   * @public
   */
;
  _proto.startUnlinkOAuth =
  /*#__PURE__*/
  function () {
    var _startUnlinkOAuth = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee27(options) {
      return _regeneratorRuntime.wrap(function (_context27) {
        while (1) switch (_context27.prev = _context27.next) {
          case 0:
            _context27.next = 1;
            return this.startSettingsAction(SettingsAction.UnlinkOAuth, options);
          case 1:
          case "end":
            return _context27.stop();
        }
      }, _callee27, this);
    }));
    function startUnlinkOAuth(_x17) {
      return _startUnlinkOAuth.apply(this, arguments);
    }
    return startUnlinkOAuth;
  }()
  /**
   * Finish settings action "unlink_oauth".
   *
   * It may reject with OAuthError.
   *
   * @public
   */
;
  _proto.finishUnlinkOAuth =
  /*#__PURE__*/
  function () {
    var _finishUnlinkOAuth = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee28() {
      return _regeneratorRuntime.wrap(function (_context28) {
        while (1) switch (_context28.prev = _context28.next) {
          case 0:
            return _context28.abrupt("return", this.finishSettingsAction());
          case 1:
          case "end":
            return _context28.stop();
        }
      }, _callee28, this);
    }));
    function finishUnlinkOAuth() {
      return _finishUnlinkOAuth.apply(this, arguments);
    }
    return finishUnlinkOAuth;
  }()
  /**
   * Finish promote anonymous user.
   *
   * It may reject with OAuthError.
   */
;
  _proto.finishPromoteAnonymousUser =
  /*#__PURE__*/
  function () {
    var _finishPromoteAnonymousUser = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee29() {
      var codeRequired;
      return _regeneratorRuntime.wrap(function (_context29) {
        while (1) switch (_context29.prev = _context29.next) {
          case 0:
            // only sessionType === "cookie" doesn't require authorization code
            codeRequired = this.sessionType === "cookie" ? false : true;
            return _context29.abrupt("return", this.baseContainer._finishAuthentication(window.location.href, codeRequired));
          case 1:
          case "end":
            return _context29.stop();
        }
      }, _callee29, this);
    }));
    function finishPromoteAnonymousUser() {
      return _finishPromoteAnonymousUser.apply(this, arguments);
    }
    return finishPromoteAnonymousUser;
  }()
  /**
   * Open the URL with the user agent authenticated with current user.
   *
   * @internal
   */
;
  _proto.openURL =
  /*#__PURE__*/
  function () {
    var _openURL = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee30(url, options) {
      var u, q, targetURL, refreshToken, appSessionToken, loginHint;
      return _regeneratorRuntime.wrap(function (_context30) {
        while (1) switch (_context30.prev = _context30.next) {
          case 0:
            u = new URL(url);
            q = u.searchParams;
            if ((options == null ? void 0 : options.uiLocales) != null) {
              q.set("ui_locales", options.uiLocales.join(" "));
            }
            u.search = "?" + q.toString();
            targetURL = u.toString(); // use authorize endpoint with app_session_token to open the setting page only
            // if session type is refresh_token
            if (!(this.sessionType === "refresh_token")) {
              _context30.next = 5;
              break;
            }
            _context30.next = 1;
            return this.tokenStorage.getRefreshToken(this.name);
          case 1:
            refreshToken = _context30.sent;
            if (refreshToken) {
              _context30.next = 2;
              break;
            }
            throw new AuthgearError("refresh token not found");
          case 2:
            _context30.next = 3;
            return this.baseContainer._getAppSessionToken(refreshToken);
          case 3:
            appSessionToken = _context30.sent;
            loginHint = "https://authgear.com/login_hint?type=app_session_token&app_session_token=".concat(encodeURIComponent(appSessionToken));
            _context30.next = 4;
            return this.authorizeEndpoint({
              redirectURI: targetURL,
              prompt: PromptOption.None,
              responseType: "none",
              loginHint: loginHint
            });
          case 4:
            targetURL = _context30.sent;
          case 5:
            if (options != null && options.openInSameTab) {
              window.location.href = targetURL;
            } else {
              window.open(targetURL, "_blank");
            }
          case 6:
          case "end":
            return _context30.stop();
        }
      }, _callee30, this);
    }));
    function openURL(_x18, _x19) {
      return _openURL.apply(this, arguments);
    }
    return openURL;
  }()
  /**
   * Open the path in authorization endpoint returned from oidc config,
   * with the user agent authenticated with current user.
   *
   * @internal
   */
  // eslint-disable-next-line class-methods-use-this
;
  _proto.openAuthgearURL =
  /*#__PURE__*/
  function () {
    var _openAuthgearURL = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee31(path, options) {
      var endpoint;
      return _regeneratorRuntime.wrap(function (_context31) {
        while (1) switch (_context31.prev = _context31.next) {
          case 0:
            _context31.next = 1;
            return this.baseContainer.getAuthorizationEndpoint();
          case 1:
            endpoint = _context31.sent;
            _context31.next = 2;
            return this.openURL("".concat(endpoint.origin).concat(path), options);
          case 2:
          case "end":
            return _context31.stop();
        }
      }, _callee31, this);
    }));
    function openAuthgearURL(_x20, _x21) {
      return _openAuthgearURL.apply(this, arguments);
    }
    return openAuthgearURL;
  }();
  _proto.open = /*#__PURE__*/function () {
    var _open = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee32(page, options) {
      return _regeneratorRuntime.wrap(function (_context32) {
        while (1) switch (_context32.prev = _context32.next) {
          case 0:
            _context32.next = 1;
            return this.openAuthgearURL(page, options);
          case 1:
          case "end":
            return _context32.stop();
        }
      }, _callee32, this);
    }));
    function open(_x22, _x23) {
      return _open.apply(this, arguments);
    }
    return open;
  }()
  /**
   * Logout.
   *
   * @remarks
   * If `force` parameter is set to `true`, all potential errors (e.g. network
   * error) would be ignored.
   *
   * `redirectURI` is required. User will be redirected to the uri after they
   * have logged out.
   *
   * @param options - Logout options
   */
;
  _proto.logout =
  /*#__PURE__*/
  function () {
    var _logout = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee33(options) {
      var _t5;
      return _regeneratorRuntime.wrap(function (_context33) {
        while (1) switch (_context33.prev = _context33.next) {
          case 0:
            if (options === void 0) {
              options = {
                redirectURI: ""
              };
            }
            if (options.redirectURI) {
              _context33.next = 1;
              break;
            }
            throw new AuthgearError("missing redirect uri");
          case 1:
            _t5 = this.sessionType;
            _context33.next = _t5 === "cookie" ? 2 : _t5 === "refresh_token" ? 4 : 6;
            break;
          case 2:
            _context33.next = 3;
            return this._logoutCookie(options);
          case 3:
            return _context33.abrupt("continue", 6);
          case 4:
            _context33.next = 5;
            return this._logoutRefreshToken(options);
          case 5:
            return _context33.abrupt("continue", 6);
          case 6:
          case "end":
            return _context33.stop();
        }
      }, _callee33, this);
    }));
    function logout(_x24) {
      return _logout.apply(this, arguments);
    }
    return logout;
  }()
  /**
   * Authenticate as an anonymous user.
   */
;
  _proto.authenticateAnonymously =
  /*#__PURE__*/
  function () {
    var _authenticateAnonymously = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee34() {
      var clientID, sessionType, userInfo, refreshToken, tokenResponse, _userInfo, _t6;
      return _regeneratorRuntime.wrap(function (_context34) {
        while (1) switch (_context34.prev = _context34.next) {
          case 0:
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context34.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            sessionType = this.sessionType;
            _t6 = this.sessionType;
            _context34.next = _t6 === "cookie" ? 2 : _t6 === "refresh_token" ? 5 : 11;
            break;
          case 2:
            _context34.next = 3;
            return this.baseContainer.apiClient.signupAnonymousUserWithoutKey(clientID, sessionType, undefined);
          case 3:
            _context34.next = 4;
            return this.baseContainer.apiClient._oidcUserInfoRequest();
          case 4:
            userInfo = _context34.sent;
            return _context34.abrupt("return", {
              userInfo: userInfo
            });
          case 5:
            _context34.next = 6;
            return this.tokenStorage.getRefreshToken(this.name);
          case 6:
            refreshToken = _context34.sent;
            _context34.next = 7;
            return this.baseContainer.apiClient.signupAnonymousUserWithoutKey(clientID, sessionType, refreshToken != null ? refreshToken : undefined);
          case 7:
            tokenResponse = _context34.sent;
            if (tokenResponse) {
              _context34.next = 8;
              break;
            }
            throw new AuthgearError("unexpected empty token response");
          case 8:
            _context34.next = 9;
            return this.baseContainer._persistTokenResponse(tokenResponse, SessionStateChangeReason.Authenticated);
          case 9:
            _context34.next = 10;
            return this.baseContainer.apiClient._oidcUserInfoRequest(tokenResponse.access_token);
          case 10:
            _userInfo = _context34.sent;
            return _context34.abrupt("return", {
              userInfo: _userInfo
            });
          case 11:
            throw new AuthgearError("unknown session type");
          case 12:
          case "end":
            return _context34.stop();
        }
      }, _callee34, this);
    }));
    function authenticateAnonymously() {
      return _authenticateAnonymously.apply(this, arguments);
    }
    return authenticateAnonymously;
  }();
  _proto._logoutRefreshToken = /*#__PURE__*/function () {
    var _logoutRefreshToken2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee35(options) {
      var _yield$this$tokenStor2;
      var refreshToken, _t7, _t8, _t9;
      return _regeneratorRuntime.wrap(function (_context35) {
        while (1) switch (_context35.prev = _context35.next) {
          case 0:
            _context35.next = 1;
            return this.tokenStorage.getRefreshToken(this.name);
          case 1:
            _t7 = _yield$this$tokenStor2 = _context35.sent;
            if (!(_t7 != null)) {
              _context35.next = 2;
              break;
            }
            _t8 = _yield$this$tokenStor2;
            _context35.next = 3;
            break;
          case 2:
            _t8 = "";
          case 3:
            refreshToken = _t8;
            if (!(refreshToken !== "")) {
              _context35.next = 8;
              break;
            }
            _context35.prev = 4;
            _context35.next = 5;
            return this.baseContainer.apiClient._oidcRevocationRequest(refreshToken);
          case 5:
            _context35.next = 7;
            break;
          case 6:
            _context35.prev = 6;
            _t9 = _context35["catch"](4);
            if (options.force) {
              _context35.next = 7;
              break;
            }
            throw _t9;
          case 7:
            _context35.next = 8;
            return this.baseContainer._clearSession(SessionStateChangeReason.Logout);
          case 8:
          case "end":
            return _context35.stop();
        }
      }, _callee35, this, [[4, 6]]);
    }));
    function _logoutRefreshToken(_x25) {
      return _logoutRefreshToken2.apply(this, arguments);
    }
    return _logoutRefreshToken;
  }();
  _proto._logoutCookie = /*#__PURE__*/function () {
    var _logoutCookie2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee36(options) {
      return _regeneratorRuntime.wrap(function (_context36) {
        while (1) switch (_context36.prev = _context36.next) {
          case 0:
            _context36.next = 1;
            return this._redirectToEndSessionEndpoint(options.redirectURI);
          case 1:
          case "end":
            return _context36.stop();
        }
      }, _callee36, this);
    }));
    function _logoutCookie(_x26) {
      return _logoutCookie2.apply(this, arguments);
    }
    return _logoutCookie;
  }() // Redirect to end_session_endpoint and logout idp session
;
  _proto._redirectToEndSessionEndpoint =
  /*#__PURE__*/
  function () {
    var _redirectToEndSessionEndpoint2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee37(redirectURI) {
      var clientID, config, query, endSessionEndpoint;
      return _regeneratorRuntime.wrap(function (_context37) {
        while (1) switch (_context37.prev = _context37.next) {
          case 0:
            clientID = this.clientID;
            if (!(clientID == null)) {
              _context37.next = 1;
              break;
            }
            throw new AuthgearError("missing client ID");
          case 1:
            _context37.next = 2;
            return this.baseContainer.apiClient._fetchOIDCConfiguration();
          case 2:
            config = _context37.sent;
            query = new URLSearchParams$2();
            if (redirectURI) {
              query.append("post_logout_redirect_uri", redirectURI);
            }
            endSessionEndpoint = "".concat(config.end_session_endpoint, "?").concat(query.toString());
            window.location.href = endSessionEndpoint;
          case 3:
          case "end":
            return _context37.stop();
        }
      }, _callee37, this);
    }));
    function _redirectToEndSessionEndpoint(_x27) {
      return _redirectToEndSessionEndpoint2.apply(this, arguments);
    }
    return _redirectToEndSessionEndpoint;
  }()
  /**
   * @internal
   */
;
  _proto.authorizeEndpoint =
  /*#__PURE__*/
  function () {
    var _authorizeEndpoint = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee38(options) {
      var _options$responseType;
      var responseType, scope, suppressIDPSessionCookie;
      return _regeneratorRuntime.wrap(function (_context38) {
        while (1) switch (_context38.prev = _context38.next) {
          case 0:
            // Use shared session cookie by default for first-party web apps.
            responseType = ((_options$responseType = options.responseType) != null ? _options$responseType : this.sessionType === "cookie") ? "none" : "code";
            scope = this.baseContainer.getAuthenticateScopes({
              requestOfflineAccess: this.sessionType === "refresh_token"
            });
            suppressIDPSessionCookie = this.sessionType === "refresh_token";
            return _context38.abrupt("return", this.baseContainer.authorizeEndpoint(_extends({}, options, {
              responseType: responseType,
              scope: scope,
              suppressIDPSessionCookie: suppressIDPSessionCookie
            })));
          case 1:
          case "end":
            return _context38.stop();
        }
      }, _callee38, this);
    }));
    function authorizeEndpoint(_x28) {
      return _authorizeEndpoint.apply(this, arguments);
    }
    return authorizeEndpoint;
  }()
  /**
   * Make authorize URL makes authorize URL based on options.
   *
   * This function will be used if developer wants to redirection in their own
   * code.
   */
;
  _proto.makeAuthorizeURL =
  /*#__PURE__*/
  function () {
    var _makeAuthorizeURL = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee39(options) {
      return _regeneratorRuntime.wrap(function (_context39) {
        while (1) switch (_context39.prev = _context39.next) {
          case 0:
            return _context39.abrupt("return", this.authorizeEndpoint(options));
          case 1:
          case "end":
            return _context39.stop();
        }
      }, _callee39, this);
    }));
    function makeAuthorizeURL(_x29) {
      return _makeAuthorizeURL.apply(this, arguments);
    }
    return makeAuthorizeURL;
  }()
  /**
   * Fetch user info.
   */
;
  _proto.fetchUserInfo =
  /*#__PURE__*/
  function () {
    var _fetchUserInfo = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee40() {
      var _this$accessToken;
      return _regeneratorRuntime.wrap(function (_context40) {
        while (1) switch (_context40.prev = _context40.next) {
          case 0:
            _context40.next = 1;
            return this.refreshAccessTokenIfNeeded();
          case 1:
            return _context40.abrupt("return", this.baseContainer._fetchUserInfo(this.sessionType === "cookie" ? undefined : (_this$accessToken = this.accessToken) != null ? _this$accessToken : ""));
          case 2:
          case "end":
            return _context40.stop();
        }
      }, _callee40, this);
    }));
    function fetchUserInfo() {
      return _fetchUserInfo.apply(this, arguments);
    }
    return fetchUserInfo;
  }()
  /**
   * implements _BaseContainerDelegate
   *
   * @internal
   */
;
  _proto.refreshAccessToken =
  /*#__PURE__*/
  function () {
    var _refreshAccessToken = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee41() {
      return _regeneratorRuntime.wrap(function (_context41) {
        while (1) switch (_context41.prev = _context41.next) {
          case 0:
            _context41.next = 1;
            return this.baseContainer._refreshAccessToken();
          case 1:
          case "end":
            return _context41.stop();
        }
      }, _callee41, this);
    }));
    function refreshAccessToken() {
      return _refreshAccessToken.apply(this, arguments);
    }
    return refreshAccessToken;
  }()
  /**
   * @public
   */
;
  _proto.refreshAccessTokenIfNeeded =
  /*#__PURE__*/
  function () {
    var _refreshAccessTokenIfNeeded = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee42() {
      return _regeneratorRuntime.wrap(function (_context42) {
        while (1) switch (_context42.prev = _context42.next) {
          case 0:
            return _context42.abrupt("return", this.baseContainer.refreshAccessTokenIfNeeded());
          case 1:
          case "end":
            return _context42.stop();
        }
      }, _callee42, this);
    }));
    function refreshAccessTokenIfNeeded() {
      return _refreshAccessTokenIfNeeded.apply(this, arguments);
    }
    return refreshAccessTokenIfNeeded;
  }()
  /**
   * Fetch function for you to call your application server.
   * The fetch function will include Authorization header in your application
   * request, and handle refresh access token automatically.
   *
   * @public
   */
;
  _proto.fetch =
  /*#__PURE__*/
  function () {
    var _fetch = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime.mark(function _callee43(input, init) {
      return _regeneratorRuntime.wrap(function (_context43) {
        while (1) switch (_context43.prev = _context43.next) {
          case 0:
            return _context43.abrupt("return", this.baseContainer.fetch(input, init));
          case 1:
          case "end":
            return _context43.stop();
        }
      }, _callee43, this);
    }));
    function fetch(_x30, _x31) {
      return _fetch.apply(this, arguments);
    }
    return fetch;
  }();
  return _createClass(WebContainer, [{
    key: "name",
    get:
    /**
     * @internal
     */

    /**
     * @internal
     */

    /**
     * @internal
     */

    /**
     * @internal
     */

    /**
     * @public
     */

    /**
     * @public
     */

    /**
     *
     * Unique ID for this container.
     * @defaultValue "default"
     *
     * @public
     */
    function get() {
      return this.baseContainer.name;
    },
    set: function set(name) {
      this.baseContainer.name = name;
    }

    /**
     * OIDC client ID
     *
     * @public
     */
  }, {
    key: "clientID",
    get: function get() {
      return this.baseContainer.clientID;
    },
    set: function set(clientID) {
      this.baseContainer.clientID = clientID;
    }

    /**
     * Is SSO enabled
     *
     * @public
     */
  }, {
    key: "isSSOEnabled",
    get: function get() {
      return this.baseContainer.isSSOEnabled;
    },
    set: function set(isSSOEnabled) {
      this.baseContainer.isSSOEnabled = isSSOEnabled;
    }

    /**
     *
     * @public
     */
  }, {
    key: "sessionState",
    get: function get() {
      return this.baseContainer.sessionState;
    },
    set: function set(sessionState) {
      this.baseContainer.sessionState = sessionState;
    }

    /**
     *
     * @public
     */
  }, {
    key: "accessToken",
    get: function get() {
      return this.baseContainer.accessToken;
    },
    set: function set(accessToken) {
      this.baseContainer.accessToken = accessToken;
    }
  }]);
}();

/**
 * Default container.
 *
 * @remarks
 * This is a global shared container, provided for convenience.
 *
 * @public
 */
var defaultContainer = new WebContainer();

export { AuthenticatorKind, AuthenticatorType, AuthgearError, CancelError, ColorScheme, DefaultDPoPProvider, ErrorName, IdentityType, LoginIDType, OAuthError, Page, PreAuthenticatedURLDeviceSecretNotFoundError, PreAuthenticatedURLIDTokenNotFoundError, PreAuthenticatedURLInsufficientScopeError, PreAuthenticatedURLNotAllowedError, PromptOption, ServerError, SessionState, SessionStateChangeReason, SettingsAction, TransientTokenStorage, VERSION, WebContainer, _BaseAPIClient, _BaseContainer, _KeyMaker, _MemoryStorageDriver, _SafeStorageDriver, _base64URLDecode, _base64URLEncode, _canReauthenticate, _decodeAuthenticators, _decodeError, _decodeIDToken, _decodeIdentities, _decodeUTF8, _decodeUserInfo, _encodeUTF8, _getAuthTime, _removeTrailingSlash, defaultContainer as default, parseAuthenticatorKind, parseAuthenticatorType, parseIdentityType, parseLoginIDType };
